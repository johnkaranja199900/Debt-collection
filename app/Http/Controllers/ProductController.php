<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\StoreRestockRequest;
use App\Http\Requests\StoreStockAdjustmentRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\SaleItem;
use App\Services\AuditService;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class ProductController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly StockService $stock,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('manage_products'), 403);

        $products = Product::with('category')
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->string('search');
                $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('sku', 'like', "%{$s}%"));
            })
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->boolean('low_stock'), fn ($q) => $q->where('type', 'product')->whereColumn('stock_quantity', '<=', 'low_stock_threshold'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'categories' => Category::where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('manage_products'), 403);

        return view('products.create', [
            'product' => new Product(['type' => 'product', 'status' => 'active', 'unit' => 'pcs', 'tax_rate' => 0]),
            'categories' => Category::where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $product = Product::create($request->validated());
        $this->audit->log('product_created', $product, [], $request->safe()->except([]));

        return redirect()->route('products.edit', $product)->with('success', 'Product created.');
    }

    public function edit(Request $request, Product $product): View
    {
        abort_unless($request->user()->can('manage_products'), 403);

        return view('products.create', [
            'product' => $product,
            'categories' => Category::where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function update(StoreProductRequest $request, Product $product): RedirectResponse
    {
        $old = $product->only(array_keys($request->validated()));
        $product->update($request->validated());
        $this->audit->log('product_updated', $product, $old, $request->validated());

        return redirect()->route('products.index')->with('success', 'Product updated.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        abort_unless($request->user()->isOwner() || $request->user()->isManager(), 403);

        $product->delete();
        $this->audit->log('product_deleted', $product);

        return redirect()->route('products.index')->with('success', 'Product deleted (soft).');
    }

    /** Stock card: current balance, ledger history and restock/adjust forms. */
    public function stock(Request $request, Product $product): View
    {
        abort_unless($request->user()->can('manage_products'), 403);

        // Sales history for this product (last 90 days) - "record of sales".
        $salesHistory = SaleItem::with('sale.customer')
            ->where('product_id', $product->id)
            ->whereHas('sale', fn ($q) => $q->where('status', '!=', 'cancelled')
                ->whereDate('sale_date', '>=', now()->subDays(90)))
            ->latest()
            ->limit(50)
            ->get();

        return view('products.stock', [
            'product' => $product,
            'movements' => $product->stockMovements()->with('user')->latest()->paginate(20),
            'salesHistory' => $salesHistory,
            'ledgerBalance' => $this->stock->ledgerBalance($product),
        ]);
    }

    /** Record a purchase / restock delivery into stock. */
    public function restock(StoreRestockRequest $request, Product $product): RedirectResponse
    {
        try {
            $movement = $this->stock->restock(
                product: $product,
                quantity: (float) $request->validated('quantity'),
                unitCost: (float) $request->validated('unit_cost'),
                userId: $request->user()->id,
                supplier: $request->validated('supplier'),
                referenceNo: $request->validated('reference_no'),
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('products.stock', $product)
            ->with('success', "Restocked {$movement->quantity} {$product->unit} of {$product->name}. New balance: {$product->fresh()->stock_quantity}.");
    }

    /** Manual correction with mandatory reason (damage, shrinkage, count...). */
    public function adjustStock(StoreStockAdjustmentRequest $request, Product $product): RedirectResponse
    {
        try {
            $movement = $this->stock->adjust(
                product: $product,
                direction: $request->directionInt(),
                quantity: (float) $request->validated('quantity'),
                reason: $request->validated('reason'),
                userId: $request->user()->id,
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('products.stock', $product)
            ->with('success', 'Stock adjustment recorded. New balance: '.$product->fresh()->stock_quantity.'.');
    }
}

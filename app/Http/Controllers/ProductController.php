<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

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
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSaleRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Services\AuditService;
use App\Services\FinancialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class SaleController extends Controller
{
    public function __construct(
        private readonly FinancialService $financial,
        private readonly AuditService $audit,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('manage_sales'), 403);

        $sales = Sale::with('customer')
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->string('search');
                $q->where(fn ($w) => $w->where('sale_number', 'like', "%{$s}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$s}%")));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest('sale_date')->latest('id')
            ->paginate(15)->withQueryString();

        return view('sales.index', compact('sales'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('manage_sales'), 403);

        return view('sales.create', [
            'customers' => Customer::where('status', 'active')->orderBy('name')->get(),
            'products' => Product::where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreSaleRequest $request): RedirectResponse
    {
        try {
            $sale = $this->financial->createSale(
                customerId: $request->integer('customer_id'),
                lines: $request->validated('items'),
                meta: $request->only(['sale_date', 'discount', 'notes']),
                createInvoice: $request->boolean('create_invoice'),
                dueDays: max($request->integer('due_days'), 0) ?: null ?? (int) (Customer::find($request->integer('customer_id'))?->payment_terms ?? 30),
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $this->audit->log('sale_created', $sale, [], ['total' => (string) $sale->total]);

        return redirect()->route('sales.show', $sale)->with('success', "Sale {$sale->sale_number} recorded.");
    }

    public function show(Request $request, Sale $sale): View
    {
        abort_unless($request->user()->can('manage_sales'), 403);

        return view('sales.show', ['sale' => $sale->load(['customer', 'items.product', 'invoices'])]);
    }

    public function cancel(Request $request, Sale $sale): RedirectResponse
    {
        abort_unless($request->user()->isOwner() || $request->user()->isManager(), 403);

        if ($sale->amount_paid > 0) {
            return back()->with('error', 'A sale with recorded payments cannot be cancelled. Record a credit note instead.');
        }

        $sale->update(['status' => 'cancelled']);

        // Restore stock through the ledger so movements stay consistent.
        $products = Product::whereIn('id', $sale->items->pluck('product_id'))->get()->keyBy('id');
        foreach ($sale->items as $item) {
            $product = $products->get($item->product_id);
            if ($product && $product->type === 'product') {
                app(\App\Services\StockService::class)->record(
                    product: $product,
                    type: \App\Models\StockMovement::SALE_CANCEL_IN,
                    direction: 1,
                    quantity: (float) $item->quantity,
                    userId: $request->user()->id,
                    reference: $sale,
                );
            }
        }

        $this->audit->log('sale_cancelled', $sale);

        return back()->with('success', 'Sale cancelled and stock restored.');
    }
}

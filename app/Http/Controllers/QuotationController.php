<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuotationRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Services\AuditService;
use App\Services\PdfService;
use App\Services\QuotationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;

class QuotationController extends Controller
{
    public function __construct(
        private readonly QuotationService $quotations,
        private readonly PdfService $pdf,
        private readonly AuditService $audit,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('manage_quotations'), 403);

        $quotations = Quotation::with('customer')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest('issue_date')->paginate(15)->withQueryString();

        return view('quotations.index', compact('quotations'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('manage_quotations'), 403);

        return view('quotations.create', [
            'customers' => Customer::where('status', 'active')->orderBy('name')->get(),
            'products' => Product::where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreQuotationRequest $request): RedirectResponse
    {
        try {
            $quotation = $this->quotations->create(
                customerId: $request->integer('customer_id'),
                lines: $request->validated('items'),
                meta: $request->only(['issue_date', 'valid_until', 'discount', 'notes']),
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $this->audit->log('quotation_created', $quotation, [], ['total' => (string) $quotation->total]);

        return redirect()->route('quotations.show', $quotation)->with('success', 'Quotation created.');
    }

    public function show(Request $request, Quotation $quotation): View
    {
        abort_unless($request->user()->can('manage_quotations'), 403);

        return view('quotations.show', ['quotation' => $quotation->load(['customer', 'items.product'])]);
    }

    public function updateStatus(Request $request, Quotation $quotation): RedirectResponse
    {
        abort_unless($request->user()->can('manage_quotations'), 403);
        $request->validate(['status' => ['required', 'in:sent,accepted,rejected,expired']]);

        $quotation->update(['status' => $request->string('status')]);
        $this->audit->log('quotation_status_changed', $quotation, [], ['status' => $quotation->status]);

        return back()->with('success', 'Quotation status updated.');
    }

    public function convert(Request $request, Quotation $quotation): RedirectResponse
    {
        abort_unless($request->user()->can('manage_sales'), 403);

        try {
            $sale = $this->quotations->convertToSale($quotation);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->audit->log('quotation_converted', $quotation, [], ['sale_number' => $sale->sale_number]);

        return redirect()->route('sales.show', $sale)->with('success', 'Quotation converted to sale '.$sale->sale_number.'.');
    }

    public function download(Request $request, Quotation $quotation): mixed
    {
        abort_unless($request->user()->can('manage_quotations'), 403);

        $path = Storage::disk('documents')->exists($cached = 'quotations/'.$quotation->quotation_number.'.pdf')
            ? $cached
            : $this->pdf->quotation($quotation);

        return Storage::disk('documents')->download($path);
    }
}

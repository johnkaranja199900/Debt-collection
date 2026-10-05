<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\AuditService;
use App\Services\PdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly PdfService $pdf,
        private readonly AuditService $audit,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('view_invoices'), 403);

        $invoices = Invoice::with('customer')
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->string('search');
                $q->where(fn ($w) => $w->where('invoice_number', 'like', "%{$s}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$s}%")));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('overdue'), fn ($q) => $q->where('status', 'overdue'))
            ->latest('issue_date')->latest('id')
            ->paginate(15)->withQueryString();

        return view('invoices.index', compact('invoices'));
    }

    public function show(Request $request, Invoice $invoice): View
    {
        abort_unless($request->user()->can('view_invoices'), 403);

        return view('invoices.show', [
            'invoice' => $invoice->load(['customer', 'payments', 'debt', 'sale.items']),
        ]);
    }

    public function download(Request $request, Invoice $invoice): mixed
    {
        abort_unless($request->user()->can('view_invoices'), 403);

        $path = Storage::disk('documents')->exists($cached = 'invoices/'.$invoice->invoice_number.'.pdf')
            ? $cached
            : $this->pdf->invoice($invoice);

        return Storage::disk('documents')->download($path);
    }

    /** Manual status transitions only - never edits money fields directly. */
    public function updateStatus(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_unless($request->user()->can('manage_invoices'), 403);
        $request->validate(['status' => ['required', 'in:sent,cancelled']]);

        if ($invoice->amount_paid > 0 && $request->string('status') === 'cancelled') {
            return back()->with('error', 'Invoices with payments cannot be cancelled.');
        }

        $old = ['status' => $invoice->status];
        $invoice->update(['status' => $request->string('status')]);
        if ($invoice->debt) {
            $invoice->debt->syncFromInvoice();
        }

        $this->audit->log('invoice_status_changed', $invoice, $old, ['status' => $invoice->status]);

        return back()->with('success', 'Invoice status updated.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PdfService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly PdfService $pdf,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('manage_payments'), 403);

        $payments = Payment::with(['customer', 'invoice'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->string('search');
                $q->where(fn ($w) => $w->where('payment_reference', 'like', "%{$s}%")
                    ->orWhere('transaction_reference', 'like', "%{$s}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$s}%")));
            })
            ->latest('payment_date')->latest('id')
            ->paginate(15)->withQueryString();

        return view('payments.index', compact('payments'));
    }

    public function create(Request $request, ?Invoice $invoice = null): View
    {
        abort_unless($request->user()->can('manage_payments'), 403);

        return view('payments.create', [
            'invoices' => Invoice::with('customer')->whereIn('status', ['sent', 'partially_paid', 'overdue'])
                ->where('balance', '>', 0)->latest()->get(),
            'selected' => $invoice,
        ]);
    }

    public function store(StorePaymentRequest $request): RedirectResponse
    {
        $invoice = Invoice::findOrFail($request->integer('invoice_id'));
        $payment = $this->payments->record($invoice, $request->validated());

        return redirect()->route('payments.show', $payment)->with('success', 'Payment recorded and ledger updated.');
    }

    public function show(Request $request, Payment $payment): View
    {
        abort_unless($request->user()->can('manage_payments'), 403);

        return view('payments.show', ['payment' => $payment->load(['customer', 'invoice'])]);
    }

    public function receipt(Request $request, Payment $payment): mixed
    {
        abort_unless($request->user()->can('manage_payments'), 403);

        $path = Storage::disk('documents')->exists($cached = 'receipts/'.$payment->payment_reference.'.pdf')
            ? $cached
            : $this->pdf->receipt($payment);

        return Storage::disk('documents')->download($path);
    }
}

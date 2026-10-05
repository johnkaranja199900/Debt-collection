<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quotation;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Illuminate\Support\Facades\Storage;

/**
 * Generates branded PDFs on the private 'documents' disk. Downloads are always
 * authorised through DocumentController - files are never publicly reachable.
 */
class PdfService
{
    public function invoice(Invoice $invoice): string
    {
        $path = 'invoices/'.$invoice->invoice_number.'.pdf';

        $pdf = DomPdf::loadView('documents.invoice', [
            'business' => Business::current(),
            'invoice' => $invoice->load(['customer', 'sale.items']),
        ])->setPaper('a4');

        Storage::disk('documents')->put($path, $pdf->output());

        return $path;
    }

    public function quotation(Quotation $quotation): string
    {
        $path = 'quotations/'.$quotation->quotation_number.'.pdf';

        $pdf = DomPdf::loadView('documents.quotation', [
            'business' => Business::current(),
            'quotation' => $quotation->load(['customer', 'items']),
        ])->setPaper('a4');

        Storage::disk('documents')->put($path, $pdf->output());

        return $path;
    }

    public function receipt(Payment $payment): string
    {
        $path = 'receipts/'.$payment->payment_reference.'.pdf';

        $pdf = DomPdf::loadView('documents.receipt', [
            'business' => Business::current(),
            'payment' => $payment->load(['customer', 'invoice']),
        ])->setPaper('a4');

        Storage::disk('documents')->put($path, $pdf->output());

        return $path;
    }

    /** Statement of account: all invoices + payments for a customer in a period. */
    public function statement(Customer $customer, string $from, string $to): string
    {
        $safeName = preg_replace('/[^A-Za-z0-9_-]/', '', $customer->name);
        $path = 'statements/'.$safeName.'-'.$from.'-'.$to.'.pdf';

        $pdf = DomPdf::loadView('documents.statement', [
            'business' => Business::current(),
            'customer' => $customer,
            'invoices' => $customer->invoices()->whereBetween('issue_date', [$from, $to])->orderBy('issue_date')->get(),
            'payments' => $customer->payments()->whereBetween('payment_date', [$from, $to])->orderBy('payment_date')->get(),
            'from' => $from,
            'to' => $to,
        ])->setPaper('a4');

        Storage::disk('documents')->put($path, $pdf->output());

        return $path;
    }
}

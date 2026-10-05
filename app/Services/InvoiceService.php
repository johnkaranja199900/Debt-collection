<?php

namespace App\Services;

use App\Models\Debt;
use App\Models\Invoice;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    /** Create an invoice from a completed sale, plus its debt record (atomic). */
    public function createFromSale(Sale $sale, ?int $dueDays = null): Invoice
    {
        return DB::transaction(function () use ($sale, $dueDays) {
            $customer = $sale->customer;
            $dueDays ??= (int) ($customer?->payment_terms ?? 30);

            $invoice = Invoice::create([
                'invoice_number' => app(DocumentNumberService::class)->invoiceNumber(),
                'customer_id' => $sale->customer_id,
                'sale_id' => $sale->id,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays($dueDays)->toDateString(),
                'subtotal' => $sale->subtotal,
                'discount' => $sale->discount,
                'tax' => $sale->tax,
                'total' => $sale->total,
                'amount_paid' => 0,
                'balance' => $sale->total,
                'status' => 'sent',
                'currency' => 'KES',
                'notes' => $sale->notes,
            ]);

            Debt::create([
                'customer_id' => $invoice->customer_id,
                'invoice_id' => $invoice->id,
                'original_amount' => $invoice->total,
                'amount_paid' => 0,
                'balance' => $invoice->balance,
                'due_date' => $invoice->due_date,
                'status' => 'current',
            ])->refreshAging();

            return $invoice;
        });
    }

    /** Mark overdue invoices/debts. Called by the scheduler. */
    public function refreshOverdue(): int
    {
        $count = 0;
        Invoice::whereIn('status', ['sent', 'partially_paid'])
            ->whereDate('due_date', '<', now()->toDateString())
            ->each(function (Invoice $invoice) use (&$count) {
                $invoice->recalculateBalance(); // sets 'overdue' when past due
                if ($invoice->debt) {
                    $invoice->debt->syncFromInvoice();
                    $count++;
                }
            });

        return $count;
    }
}

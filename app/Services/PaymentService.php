<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Payment recording with full data consistency (master spec 63):
 * payment -> invoice -> debt -> sale, all inside one transaction.
 */
class PaymentService
{
    public function __construct(private readonly AuditService $audit) {}

    public function record(Invoice $invoice, array $data): Payment
    {
        if (in_array($invoice->status, ['draft', 'cancelled'], true)) {
            throw ValidationException::withMessages(['invoice' => 'This invoice cannot receive payments.']);
        }

        // Never overpay beyond the server-computed balance.
        $max = round((float) $invoice->balance, 2);
        if ($max <= 0) {
            throw ValidationException::withMessages(['amount' => 'This invoice is already fully paid.']);
        }
        if ((float) $data['amount'] > $max) {
            throw ValidationException::withMessages(['amount' => "Amount exceeds outstanding balance of KSh {$max}."]);
        }

        return DB::transaction(function () use ($invoice, $data) {
            $payment = Payment::create([
                'customer_id' => $invoice->customer_id,
                'invoice_id' => $invoice->id,
                'amount' => round((float) $data['amount'], 2),
                'payment_method' => $data['payment_method'],
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'payment_date' => $data['payment_date'],
                'status' => 'completed',
                'source' => 'manual',
                'notes' => $data['notes'] ?? null,
            ]);

            $invoice->recalculateBalance();

            if ($invoice->debt) {
                $invoice->debt->syncFromInvoice();
            }

            if ($invoice->sale) {
                $sale = $invoice->sale;
                $paid = (float) Invoice::where('sale_id', $sale->id)
                    ->whereIn('status', ['sent', 'partially_paid', 'overdue', 'paid'])
                    ->sum('amount_paid');
                $sale->amount_paid = round($paid, 2);
                $sale->balance = round((float) $sale->total - $paid, 2);
                $sale->payment_status = match (true) {
                    $sale->balance <= 0 => 'paid',
                    $paid > 0 => 'partially_paid',
                    default => 'unpaid',
                };
                $sale->save();
            }

            $this->audit->log('payment_recorded', $payment, [], [
                'invoice_number' => $invoice->invoice_number,
                'amount' => (string) $payment->amount,
                'method' => $payment->payment_method,
            ]);

            return $payment;
        });
    }
}

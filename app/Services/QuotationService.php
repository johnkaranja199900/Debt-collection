<?php

namespace App\Services;

use App\Models\Quotation;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuotationService
{
    public function create(int $customerId, array $lines, array $meta): Quotation
    {
        return DB::transaction(function () use ($customerId, $lines, $meta) {
            $priced = app(FinancialService::class)->priceLines($lines, (float) ($meta['discount'] ?? 0));

            $quotation = Quotation::create([
                'quotation_number' => app(DocumentNumberService::class)->quotationNumber(),
                'customer_id' => $customerId,
                'issue_date' => $meta['issue_date'],
                'valid_until' => $meta['valid_until'],
                'subtotal' => $priced['subtotal'],
                'discount' => $priced['discount'],
                'tax' => $priced['taxTotal'],
                'total' => $priced['total'],
                'status' => 'draft',
                'notes' => $meta['notes'] ?? null,
            ]);

            $quotation->items()->createMany($priced['lines']);

            return $quotation;
        });
    }

    /** Quotation -> Sale -> Invoice flow (master spec 16). */
    public function convertToSale(Quotation $quotation, bool $createInvoice = true): Sale
    {
        if (! in_array($quotation->status, ['accepted', 'sent', 'draft'], true)) {
            throw ValidationException::withMessages(['status' => 'Only draft/sent/accepted quotations can be converted.']);
        }

        return DB::transaction(function () use ($quotation, $createInvoice) {
            $lines = $quotation->items()->get()->map(fn ($item) => [
                'product_id' => $item->product_id,
                'quantity' => (float) $item->quantity,
                'discount' => (float) $item->discount,
            ])->all();

            $sale = app(FinancialService::class)->createSale(
                customerId: $quotation->customer_id,
                lines: $lines,
                meta: [
                    'sale_date' => now()->toDateString(),
                    'discount' => (float) $quotation->discount,
                    'notes' => 'Converted from quotation '.$quotation->quotation_number,
                ],
                createInvoice: $createInvoice,
                dueDays: (int) ($quotation->customer?->payment_terms ?? 30),
            );

            $quotation->update(['status' => 'converted']);

            return $sale;
        });
    }
}

<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * All money math lives here and runs server-side only (master spec 62).
 * Money is rounded to 2 decimals with bcmath-safe arithmetic via round().
 */
class FinancialService
{
    /**
     * Build line data from trusted product prices. Returns lines plus totals.
     *
     * @param array<int, array{product_id:int, quantity:float, discount?:float}> $requestedLines
     */
    public function priceLines(array $requestedLines, float $documentDiscount = 0): array
    {
        $products = Product::whereIn('id', array_column($requestedLines, 'product_id'))
            ->where('status', 'active')
            ->get()
            ->keyBy('id');

        $lines = [];
        $subtotal = 0.0;
        $taxTotal = 0.0;

        foreach ($requestedLines as $line) {
            $product = $products->get($line['product_id']);
            if (! $product) {
                throw new RuntimeException("Product #{$line['product_id']} is unavailable.");
            }

            $quantity = round((float) $line['quantity'], 2);
            $unitPrice = round((float) $product->selling_price, 2);
            $lineDiscount = round(min((float) ($line['discount'] ?? 0), $quantity * $unitPrice), 2);
            $net = $quantity * $unitPrice - $lineDiscount;
            $tax = round($net * (float) $product->tax_rate / 100, 2);
            $total = round($net + $tax, 2);

            $subtotal += $net;
            $taxTotal += $tax;

            $lines[] = [
                'product_id' => $product->id,
                'description' => $product->name,
                'unit' => $product->unit,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount' => $lineDiscount,
                'tax_rate' => (float) $product->tax_rate,
                'tax' => $tax,
                'total' => $total,
                'cost_price' => round((float) $product->cost_price, 2),
            ];
        }

        $subtotal = round($subtotal, 2);
        $documentDiscount = round(min($documentDiscount, $subtotal), 2);
        // Proportionally reduce tax when a document-level discount is applied.
        $taxTotal = round($subtotal > 0 ? $taxTotal * (1 - $documentDiscount / $subtotal) : 0, 2);
        $total = round($subtotal - $documentDiscount + $taxTotal, 2);

        return compact('lines', 'subtotal', 'taxTotal', 'total') + ['discount' => $documentDiscount];
    }

    /** Create a sale (and optionally an invoice + debt) atomically. */
    public function createSale(int $customerId, array $lines, array $meta, bool $createInvoice, int $dueDays): Sale
    {
        return DB::transaction(function () use ($customerId, $lines, $meta, $createInvoice, $dueDays) {
            $priced = $this->priceLines($lines, (float) ($meta['discount'] ?? 0));

            $sale = Sale::create([
                'sale_number' => app(DocumentNumberService::class)->saleNumber(),
                'customer_id' => $customerId,
                'sale_date' => $meta['sale_date'],
                'subtotal' => $priced['subtotal'],
                'discount' => $priced['discount'],
                'tax' => $priced['taxTotal'],
                'total' => $priced['total'],
                'amount_paid' => 0,
                'balance' => $priced['total'],
                'status' => 'completed',
                'payment_status' => 'unpaid',
                'notes' => $meta['notes'] ?? null,
            ]);

            $sale->items()->createMany($priced['lines']);

            // Decrement stock for physical products.
            foreach ($priced['lines'] as $line) {
                DB::table('products')->where('id', $line['product_id'])
                    ->where('type', 'product')
                    ->update(['stock_quantity' => DB::raw('GREATEST(stock_quantity - '.$line['quantity'].', 0)')]);
            }

            if ($createInvoice) {
                app(InvoiceService::class)->createFromSale($sale, $dueDays);
            }

            return $sale;
        });
    }
}

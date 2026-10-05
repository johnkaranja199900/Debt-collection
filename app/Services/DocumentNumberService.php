<?php

namespace App\Services;

use App\Models\Business;
use Illuminate\Support\Facades\DB;

/**
 * Sequential, gap-free document numbers per prefix+period, generated inside a
 * locked transaction so concurrent requests cannot collide.
 */
class DocumentNumberService
{
    public function next(string $table, string $column, string $prefix): string
    {
        return DB::transaction(function () use ($table, $column, $prefix) {
            $period = now()->format('Ym');
            $pattern = $prefix.'-'.$period.'-%';

            $last = DB::table($table)
                ->where($column, 'like', $pattern)
                ->lockForUpdate()
                ->max($column);

            $next = $last ? ((int) substr($last, -4)) + 1 : 1;

            return $prefix.'-'.$period.'-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        }, 3);
    }

    public function invoiceNumber(): string
    {
        return $this->next('invoices', 'invoice_number', $this->prefix('invoice_prefix', 'INV'));
    }

    public function quotationNumber(): string
    {
        return $this->next('quotations', 'quotation_number', $this->prefix('quotation_prefix', 'QTN'));
    }

    public function saleNumber(): string
    {
        return $this->next('sales', 'sale_number', 'SAL');
    }

    private function prefix(string $key, string $default): string
    {
        return Business::current()?->{$key} ?? $default;
    }
}

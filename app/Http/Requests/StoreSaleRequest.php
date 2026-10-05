<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Only product references, quantities and optional per-line discounts are trusted.
 * Prices, taxes and totals are recomputed server-side (master spec section 62).
 */
class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage_sales');
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'sale_date' => ['required', 'date'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'create_invoice' => ['nullable', 'boolean'],
            'due_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}

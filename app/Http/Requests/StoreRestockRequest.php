<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRestockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage_products');
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'unit_cost' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'supplier' => ['nullable', 'string', 'max:120'],
            'reference_no' => ['nullable', 'string', 'max:60'],
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.gt' => 'Restock quantity must be greater than zero.',
            'unit_cost.required' => 'Enter the cost you paid per unit (delivery note / invoice).',
        ];
    }
}

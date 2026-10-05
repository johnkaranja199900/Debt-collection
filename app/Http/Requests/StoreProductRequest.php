<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage_products');
    }

    public function rules(): array
    {
        $id = $this->route('product')?->id ?? 0;

        return [
            'sku' => ['required', 'string', 'max:60', 'unique:products,sku,'.$id],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', 'in:product,service'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'cost_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'selling_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'stock_quantity' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'low_stock_threshold' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'unit' => ['required', 'string', 'max:20'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}

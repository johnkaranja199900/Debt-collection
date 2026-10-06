<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Adjustments change stock without a sale/purchase - manager level.
        return $this->user()->isOwner() || $this->user()->isManager();
    }

    public function rules(): array
    {
        return [
            'direction' => ['required', 'in:in,out'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ];
    }

    public function directionInt(): int
    {
        return $this->validated('direction') === 'in' ? 1 : -1;
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage_customers');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+]{7,20}$/'],
            'alternative_phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+]{7,20}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'physical_address' => ['nullable', 'string', 'max:255'],
            'postal_address' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'credit_limit' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'payment_terms' => ['nullable', 'integer', 'min:0', 'max:365'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}

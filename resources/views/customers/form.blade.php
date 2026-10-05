@php $c = $customer ?? null; $input = fn ($k, $d = '') => old($k, $c?->$k ?? $d); @endphp
<div class="grid gap-4 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-gray-700">Full name *</label>
        <input name="name" value="{{ $input('name') }}" required class="mt-1 block w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700">Business name</label>
        <input name="business_name" value="{{ $input('business_name') }}" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700">Phone *</label>
        <input name="phone" value="{{ $input('phone') }}" required pattern="[0-9+]{7,20}" placeholder="+2547XXXXXXXX" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
        @error('phone')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700">Alternative phone</label>
        <input name="alternative_phone" value="{{ $input('alternative_phone') }}" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700">Email</label>
        <input type="email" name="email" value="{{ $input('email') }}" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700">Tax number / KRA PIN</label>
        <input name="tax_number" value="{{ $input('tax_number') }}" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700">Physical address</label>
        <input name="physical_address" value="{{ $input('physical_address') }}" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700">Postal address</label>
        <input name="postal_address" value="{{ $input('postal_address') }}" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700">Credit limit ({{ \App\Models\Business::current()?->currency ?? 'KES' }})</label>
        <input type="number" step="0.01" min="0" name="credit_limit" value="{{ $input('credit_limit', 0) }}" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700">Payment terms (days)</label>
        <input type="number" min="0" max="365" name="payment_terms" value="{{ $input('payment_terms', 30) }}" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700">Status</label>
        <select name="status" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
            <option value="active" @selected(($c?->status ?? 'active') === 'active')>Active</option>
            <option value="inactive" @selected(($c?->status ?? '') === 'inactive')>Inactive</option>
        </select>
    </div>
    <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-gray-700">Notes</label>
        <textarea name="notes" rows="3" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">{{ $input('notes') }}</textarea>
    </div>
</div>

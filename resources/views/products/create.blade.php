<x-layouts.app :title="$product->exists ? 'Edit Product' : 'Add Product'">
    <div class="mx-auto max-w-2xl px-4 py-4">
        <h1 class="text-xl font-bold mb-4">{{ $product->exists ? 'Edit '.$product->name : 'Add product or service' }}</h1>

        <form method="POST" action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}"
              class="space-y-4 rounded-xl bg-white p-4 shadow ring-1 ring-gray-100">
            @csrf
            @if ($product->exists) @method('PUT') @endif

            <div x-data="{ type: '{{ old('type', $product->type ?? 'product') }}' }">
                <label class="block text-sm font-medium text-gray-700 mb-1">Type <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="flex min-h-[44px] items-center justify-center gap-2 rounded-lg border text-sm font-medium cursor-pointer"
                           :class="type === 'product' ? 'border-emerald-600 bg-emerald-50 text-emerald-800' : 'border-gray-300 bg-white'">
                        <input type="radio" name="type" value="product" x-model="type" class="sr-only"> Product (tracked stock)
                    </label>
                    <label class="flex min-h-[44px] items-center justify-center gap-2 rounded-lg border text-sm font-medium cursor-pointer"
                           :class="type === 'service' ? 'border-emerald-600 bg-emerald-50 text-emerald-800' : 'border-gray-300 bg-white'">
                        <input type="radio" name="type" value="service" x-model="type" class="sr-only"> Service (no stock)
                    </label>
                </div>
                @error('type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror

                <div x-show="type === 'product'" class="mt-3 rounded-lg bg-amber-50 p-3 text-xs text-amber-800 ring-1 ring-amber-200">
                    Products track stock. Use the <strong>Stock &amp; Sales</strong> page after saving to record restocks and corrections.
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-input label="SKU / Code" name="sku" :value="$product->sku" required />
                <x-input label="Name" name="name" :value="$product->name" required />
            </div>

            <x-select label="Category" name="category_id" :options="$categories->pluck('name', 'id')->prepend('None', '')" :value="$product->category_id" />

            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea id="description" name="description" rows="2"
                          class="w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500">{{ old('description', $product->description) }}</textarea>
                @error('description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <x-input label="Cost price (KSh)" name="cost_price" type="number" step="0.01" min="0" :value="$product->cost_price" required inputmode="decimal" />
                <x-input label="Selling price (KSh)" name="selling_price" type="number" step="0.01" min="0" :value="$product->selling_price" required inputmode="decimal" />
                <x-input label="Tax rate (%)" name="tax_rate" type="number" step="0.01" min="0" max="100" :value="$product->tax_rate ?? 0" required inputmode="decimal" />
                <x-input label="Unit" name="unit" :value="$product->unit ?? 'pcs'" required placeholder="pcs, kg, litres, box…" />
                <x-input label="Opening stock qty" name="stock_quantity" type="number" step="0.01" min="0" :value="$product->stock_quantity ?? 0" required inputmode="decimal" />
                <x-input label="Low-stock alert level" name="low_stock_threshold" type="number" step="0.01" min="0" :value="$product->low_stock_threshold ?? 0" required inputmode="decimal" />
            </div>
            <p class="text-xs text-gray-500">Note: opening stock entered here is the starting balance. All later changes should go through restocks, sales or adjustments so the ledger stays accurate.</p>

            <x-select label="Status" name="status" :options="['active' => 'Active', 'inactive' => 'Inactive']" :value="$product->status ?? 'active'" required />

            <div class="flex gap-3 pt-2">
                <a href="{{ route('products.index') }}" class="inline-flex min-h-[44px] flex-1 items-center justify-center rounded-lg bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-800">Cancel</a>
                <button class="inline-flex min-h-[44px] flex-1 items-center justify-center rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                    {{ $product->exists ? 'Save changes' : 'Create product' }}
                </button>
            </div>
        </form>

        @if ($product->exists && $product->type === 'product')
            <div class="mt-4 rounded-xl bg-emerald-50 p-4 ring-1 ring-emerald-100">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-emerald-900">Current stock: {{ number_format((float) $product->stock_quantity, 2) }} {{ $product->unit }}</p>
                        @if ($product->isLowStock())<p class="text-xs font-medium text-red-600">Below low-stock threshold ({{ $product->low_stock_threshold }})</p>@endif
                    </div>
                    <a href="{{ route('products.stock', $product) }}" class="inline-flex min-h-[44px] items-center rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Manage stock →</a>
                </div>
            </div>
        @endif
    </div>
</x-layouts.app>

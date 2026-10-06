<x-layouts.app :title="'Stock — '.$product->name">
    @php
        $movementLabels = [
            'sale_out' => ['Sale', 'bg-red-50 text-red-700'],
            'sale_cancel_in' => ['Cancel return', 'bg-green-50 text-green-700'],
            'purchase_in' => ['Restock', 'bg-emerald-50 text-emerald-700'],
            'adjustment' => ['Adjustment', 'bg-yellow-50 text-yellow-800'],
        ];
    @endphp

    <div class="mx-auto max-w-4xl px-4 py-4 space-y-4">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h1 class="text-xl font-bold truncate">{{ $product->name }}</h1>
                <p class="text-xs text-gray-500">{{ $product->sku }} · {{ $product->category?->name ?? 'No category' }}</p>
            </div>
            <a href="{{ route('products.edit', $product) }}" class="inline-flex min-h-[44px] shrink-0 items-center rounded-lg bg-gray-100 px-3 text-sm font-medium">Edit</a>
        </div>

        {{-- Balance summary cards (mobile-first) --}}
        <div class="grid grid-cols-2 gap-3">
            <div @class(['rounded-xl p-4 shadow ring-1', $product->isLowStock() ? 'bg-red-50 ring-red-200' : 'bg-white ring-gray-100'])>
                <p class="text-xs uppercase tracking-wide text-gray-500">Stock on hand</p>
                <p class="mt-1 text-2xl font-bold {{ $product->isLowStock() ? 'text-red-700' : 'text-emerald-800' }}">
                    {{ number_format((float) $product->stock_quantity, 2) }}
                    <span class="text-sm font-medium text-gray-500">{{ $product->unit }}</span>
                </p>
                @if ($product->isLowStock())
                    <p class="mt-1 text-xs font-semibold text-red-600">⚠ Low (alert at {{ number_format((float) $product->low_stock_threshold, 0) }})</p>
                @endif
            </div>
            <div class="rounded-xl bg-white p-4 shadow ring-1 ring-gray-100">
                <p class="text-xs uppercase tracking-wide text-gray-500">Ledger balance</p>
                <p class="mt-1 text-2xl font-bold {{ abs($ledgerBalance - (float) $product->stock_quantity) < 0.01 ? 'text-gray-800' : 'text-orange-600' }}">
                    {{ number_format($ledgerBalance, 2) }}
                </p>
                <p class="mt-1 text-xs text-gray-500">
                    {{ abs($ledgerBalance - (float) $product->stock_quantity) < 0.01 ? 'Matches stock ✓' : 'Differs from stock (pre-ledger movements)' }}
                </p>
            </div>
        </div>

        {{-- Restock & adjustment actions --}}
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <form method="POST" action="{{ route('products.restock', $product) }}"
                  class="space-y-3 rounded-xl bg-white p-4 shadow ring-1 ring-gray-100">
                @csrf
                <h2 class="font-semibold text-emerald-800">📦 Record restock / purchase</h2>
                <p class="text-xs text-gray-500">When goods arrive from a supplier. Cost updates your average cost automatically.</p>
                <div class="grid grid-cols-2 gap-3">
                    <x-input label="Quantity in" name="quantity" type="number" step="0.01" min="0.01" required inputmode="decimal" placeholder="e.g. 50" />
                    <x-input label="Unit cost paid (KSh)" name="unit_cost" type="number" step="0.01" min="0" required inputmode="decimal" placeholder="e.g. 450" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <x-input label="Supplier (optional)" name="supplier" placeholder="e.g. Nail Corp" />
                    <x-input label="Delivery ref (optional)" name="reference_no" placeholder="DN number" />
                </div>
                <button class="w-full inline-flex min-h-[44px] items-center justify-center rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Add to stock</button>
            </form>

            @can('manage_integrations') {{-- owner/manager gate mirrors StockAdjustmentRequest --}}
            <form method="POST" action="{{ route('products.adjust-stock', $product) }}"
                  class="space-y-3 rounded-xl bg-white p-4 shadow ring-1 ring-gray-100">
                @csrf
                <h2 class="font-semibold text-yellow-800">⚖️ Stock adjustment</h2>
                <p class="text-xs text-gray-500">Corrections for damage, theft or stock-count differences. Reason is mandatory and audited.</p>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Direction</label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="flex min-h-[44px] items-center justify-center rounded-lg border border-gray-300 text-sm cursor-pointer has-[:checked]:border-emerald-600 has-[:checked]:bg-emerald-50">
                                <input type="radio" name="direction" value="in" class="sr-only" checked> Increase
                            </label>
                            <label class="flex min-h-[44px] items-center justify-center rounded-lg border border-gray-300 text-sm cursor-pointer has-[:checked]:border-red-600 has-[:checked]:bg-red-50">
                                <input type="radio" name="direction" value="out" class="sr-only"> Decrease
                            </label>
                        </div>
                    </div>
                    <x-input label="Quantity" name="quantity" type="number" step="0.01" min="0.01" required inputmode="decimal" />
                </div>
                <x-input label="Reason" name="reason" required placeholder="e.g. Damaged during transport – 3 bags" />
                <button class="w-full inline-flex min-h-[44px] items-center justify-center rounded-lg bg-yellow-600 px-4 py-2 text-sm font-semibold text-white hover:bg-yellow-700">Apply adjustment</button>
            </form>
            @endcan
        </div>

        {{-- Movements ledger --}}
        <section class="rounded-xl bg-white shadow ring-1 ring-gray-100">
            <h2 class="border-b px-4 py-3 font-semibold">Stock movement history</h2>
            <div class="divide-y divide-gray-100">
                @forelse ($movements as $m)
                    <div class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            @php [$label, $cls] = $movementLabels[$m->type] ?? [ucfirst($m->type), 'bg-gray-100 text-gray-700']; @endphp
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $cls }}">{{ $label }}</span>
                            <p class="mt-0.5 text-xs text-gray-500 truncate">
                                {{ $m->created_at->format('d M Y H:i') }}
                                · by {{ $m->user?->name ?? 'system' }}
                                @if($m->reason)· {{ $m->reason }}@endif
                                @if($m->notes)· {{ $m->notes }}@endif
                            </p>
                        </div>
                        <p class="shrink-0 font-semibold {{ $m->direction > 0 ? 'text-emerald-700' : 'text-red-700' }}">
                            {{ $m->direction > 0 ? '+' : '−' }}{{ number_format((float) $m->quantity, 2) }} {{ $product->unit }}
                        </p>
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-sm text-gray-500">No movements yet. Sales, restocks and adjustments will appear here.</p>
                @endforelse
            </div>
            <div class="px-4 py-3">{{ $movements->links() }}</div>
        </section>

        {{-- Sales record --}}
        <section class="rounded-xl bg-white shadow ring-1 ring-gray-100">
            <h2 class="border-b px-4 py-3 font-semibold">Sales of this product <span class="text-xs font-normal text-gray-500">(last 90 days)</span></h2>
            <div class="divide-y divide-gray-100">
                @forelse ($salesHistory as $item)
                    <div class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">
                                {{ $item->sale?->customer?->name ?? 'Walk-in' }}
                                <span class="text-xs text-gray-400">· {{ $item->sale?->sale_number }}</span>
                            </p>
                            <p class="text-xs text-gray-500">{{ $item->sale?->sale_date?->format('d M Y') }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-sm font-semibold">{{ number_format((float) $item->quantity, 2) }} {{ $product->unit }}</p>
                            <p class="text-xs text-emerald-700">KSh {{ number_format((float) $item->total, 2) }}</p>
                        </div>
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-sm text-gray-500">No sales recorded for this product in the last 90 days.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.app>

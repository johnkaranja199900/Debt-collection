<x-layouts.app title="Products & Services">
    <div class="mx-auto max-w-7xl px-4 py-4 space-y-4">
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-xl font-bold">Products &amp; Services</h1>
            @can('manage_products')
                <a href="{{ route('products.create') }}"
                   class="inline-flex min-h-[44px] items-center rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                    + Add
                </a>
            @endcan
        </div>

        {{-- Mobile-first filters --}}
        <form method="GET" action="{{ route('products.index') }}" class="grid grid-cols-2 gap-2 sm:flex sm:items-end">
            <div class="col-span-2 sm:flex-1">
                <x-input label="Search" name="search" :value="request('search')" placeholder="Name or SKU" />
            </div>
            <x-select label="Category" name="category_id" :options="$categories->pluck('name', 'id')->prepend('All', '')" :value="request('category_id')" />
            <div>
                <label for="type" class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                <select id="type" name="type" class="w-full rounded-lg border-gray-300 shadow-sm min-h-[44px] bg-white">
                    <option value="">All</option>
                    <option value="product" @selected(request('type') === 'product')>Product</option>
                    <option value="service" @selected(request('type') === 'service')>Service</option>
                </select>
            </div>
            <label class="col-span-2 flex items-center gap-2 text-sm min-h-[44px]">
                <input type="checkbox" name="low_stock" value="1" @checked(request()->boolean('low_stock')) class="rounded border-gray-300 text-emerald-600 h-5 w-5">
                Low stock only
            </label>
            <button class="col-span-2 sm:col-span-1 inline-flex min-h-[44px] items-center justify-center rounded-lg bg-gray-800 px-4 py-2 text-sm font-semibold text-white">Filter</button>
        </form>

        {{-- Cards on mobile, table on desktop --}}
        <div class="space-y-3 md:hidden">
            @forelse ($products as $product)
                <div class="rounded-xl bg-white p-4 shadow ring-1 ring-gray-100">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-semibold truncate">{{ $product->name }}</p>
                            <p class="text-xs text-gray-500">{{ $product->sku }} · {{ ucfirst($product->type) }}{{ $product->category ? ' · '.$product->category->name : '' }}</p>
                        </div>
                        <x-status-badge :status="$product->status" />
                    </div>
                    <div class="mt-2 flex items-center justify-between text-sm">
                        <span class="font-semibold text-emerald-700">KSh {{ number_format((float) $product->selling_price, 2) }}</span>
                        @if ($product->type === 'product')
                            <span @class(['text-xs font-medium', 'text-red-600' => $product->isLowStock(), 'text-gray-600' => !$product->isLowStock()])>
                                Stock: {{ number_format((float) $product->stock_quantity, 2) }} {{ $product->unit }}
                                @if($product->isLowStock()) ⚠️ @endif
                            </span>
                        @else
                            <span class="text-xs text-gray-400">Service</span>
                        @endif
                    </div>
                    <div class="mt-3 flex gap-2">
                        @if ($product->type === 'product')
                            <a href="{{ route('products.stock', $product) }}" class="flex-1 inline-flex min-h-[44px] items-center justify-center rounded-lg bg-emerald-50 text-emerald-800 text-sm font-medium ring-1 ring-emerald-200">Stock &amp; Sales</a>
                        @endif
                        @can('manage_products')
                            <a href="{{ route('products.edit', $product) }}" class="flex-1 inline-flex min-h-[44px] items-center justify-center rounded-lg bg-gray-100 text-gray-800 text-sm font-medium">Edit</a>
                        @endcan
                    </div>
                </div>
            @empty
                <p class="rounded-lg bg-white p-6 text-center text-sm text-gray-500 shadow">No products yet. Add your first product or service.</p>
            @endforelse
        </div>

        <div class="hidden md:block overflow-x-auto rounded-xl bg-white shadow ring-1 ring-gray-100">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Name / SKU</th>
                        <th class="px-4 py-3">Category</th>
                        <th class="px-4 py-3">Cost</th>
                        <th class="px-4 py-3">Price</th>
                        <th class="px-4 py-3">Tax</th>
                        <th class="px-4 py-3">Stock</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($products as $product)
                        <tr>
                            <td class="px-4 py-3"><span class="font-medium">{{ $product->name }}</span><br><span class="text-xs text-gray-500">{{ $product->sku }} · {{ ucfirst($product->type) }}</span></td>
                            <td class="px-4 py-3">{{ $product->category?->name ?? '—' }}</td>
                            <td class="px-4 py-3">{{ number_format((float) $product->cost_price, 2) }}</td>
                            <td class="px-4 py-3 font-semibold text-emerald-700">{{ number_format((float) $product->selling_price, 2) }}</td>
                            <td class="px-4 py-3">{{ rtrim(rtrim(number_format((float) $product->tax_rate, 2), '0'), '.') }}%</td>
                            <td class="px-4 py-3">
                                @if ($product->type === 'product')
                                    <span @class(['font-medium', 'text-red-600' => $product->isLowStock()])>{{ number_format((float) $product->stock_quantity, 2) }} {{ $product->unit }}</span>
                                @else — @endif
                            </td>
                            <td class="px-4 py-3"><x-status-badge :status="$product->status" /></td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                @if ($product->type === 'product')
                                    <a href="{{ route('products.stock', $product) }}" class="text-emerald-700 hover:underline">Stock</a>
                                @endif
                                @can('manage_products')
                                    <a href="{{ route('products.edit', $product) }}" class="ml-3 text-gray-700 hover:underline">Edit</a>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div>{{ $products->links() }}</div>
    </div>
</x-layouts.app>

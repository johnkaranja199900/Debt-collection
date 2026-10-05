<x-app-layout>
    <x-slot name="title">Dashboard</x-slot>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-bold">Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening') }}, {{ auth()->user()->name->before(' ') ?? auth()->user()->name }}</h1>
            <div class="flex gap-2">
                @can('manage_sales')<a href="{{ route('sales.create') }}" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">+ New sale</a>@endcan
                @can('manage_customers')<a href="{{ route('customers.create') }}" class="rounded-lg bg-white px-3 py-2 text-sm font-semibold text-emerald-700 ring-1 ring-emerald-200 hover:bg-emerald-50">+ Customer</a>@endcan
            </div>
        </div>

        {{-- Money cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-100 p-5">
                <p class="text-xs uppercase tracking-wide text-gray-500">Revenue this month</p>
                <p class="mt-1 text-2xl font-bold">{{ number_format($pl['revenue'], 2) }}</p>
                @if ($trends['revenue_growth'] !== null)
                    <p class="mt-1 text-xs {{ $trends['revenue_growth'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $trends['revenue_growth'] >= 0 ? '▲' : '▼' }} {{ number_format(abs($trends['revenue_growth']), 1) }}% vs last month
                    </p>
                @endif
            </div>
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-100 p-5">
                <p class="text-xs uppercase tracking-wide text-gray-500">Net profit this month</p>
                <p class="mt-1 text-2xl font-bold {{ $pl['net_profit'] < 0 ? 'text-red-600' : 'text-emerald-700' }}">{{ number_format($pl['net_profit'], 2) }}</p>
                <p class="mt-1 text-xs text-gray-500">Margin {{ number_format($pl['profit_margin'], 1) }}%</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-100 p-5">
                <p class="text-xs uppercase tracking-wide text-gray-500">Total outstanding</p>
                <a href="{{ route('debts.index') }}" class="mt-1 block text-2xl font-bold text-blue-700 hover:underline">{{ number_format($debts['total_outstanding'], 2) }}</a>
                <p class="mt-1 text-xs text-gray-500">{{ $debtCount }} open debt{{ $debtCount === 1 ? '' : 's' }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-100 p-5">
                <p class="text-xs uppercase tracking-wide text-gray-500">Overdue</p>
                <a href="{{ route('debts.index', ['overdue_only' => 1]) }}" class="mt-1 block text-2xl font-bold text-red-600 hover:underline">{{ number_format($debts['total_overdue'], 2) }}</a>
                <p class="mt-1 text-xs text-gray-500">{{ $overdueCount }} overdue</p>
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-6">
            {{-- Insights --}}
            <div class="lg:col-span-2 space-y-6">
                @if (!empty($insights))
                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-5">
                        <h2 class="font-semibold text-amber-900 mb-2">💡 Business insights</h2>
                        <ul class="list-disc pl-5 space-y-1 text-sm text-amber-900">
                            @foreach ($insights as $insight)
                                <li>{{ $insight }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Recent invoices --}}
                <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-100">
                    <div class="flex items-center justify-between px-5 py-4 border-b">
                        <h2 class="font-semibold">Recent invoices</h2>
                        <a href="{{ route('invoices.index') }}" class="text-sm text-emerald-700 hover:underline">View all</a>
                    </div>
                    <table class="w-full text-sm">
                        <tbody>
                        @forelse ($recentInvoices as $invoice)
                            <tr class="border-b last:border-0 hover:bg-gray-50">
                                <td class="px-5 py-3">
                                    <a class="font-medium text-emerald-700 hover:underline" href="{{ route('invoices.show', $invoice) }}">{{ $invoice->invoice_number }}</a>
                                    <div class="text-gray-500">{{ $invoice->customer?->name }}</div>
                                </td>
                                <td class="px-5 py-3 text-right font-mono">{{ number_format($invoice->total, 2) }}</td>
                                <td class="px-5 py-3 text-right"><x-status-badge :status="$invoice->status" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-5 py-8 text-center text-gray-500">No invoices yet. Record a credit sale to create one.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Right column --}}
            <div class="space-y-6">
                <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-100 p-5">
                    <h2 class="font-semibold mb-3">Top customers</h2>
                    <ol class="space-y-2 text-sm">
                        @forelse ($topCustomers as $c)
                            <li class="flex justify-between gap-2">
                                <span class="truncate">{{ $c->name }}</span>
                                <span class="font-mono text-gray-600">{{ number_format($c->revenue, 0) }}</span>
                            </li>
                        @empty
                            <li class="text-gray-500">No sales yet.</li>
                        @endforelse
                    </ol>
                </div>
                <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-100 p-5">
                    <h2 class="font-semibold mb-3">Aging of receivables</h2>
                    @foreach (($debts['aging'] ?? []) as $bucket => $amount)
                        <div class="flex justify-between text-sm py-1">
                            <span>{{ str_replace('_', '-', $bucket) }}</span>
                            <span class="font-mono">{{ number_format($amount, 0) }}</span>
                        </div>
                    @endforeach
                    @if (empty($debts['aging']))<p class="text-sm text-gray-500">Nothing overdue — great!</p>@endif
                    @can('view_reports')<a href="{{ route('reports.aging') }}" class="mt-2 inline-block text-sm text-emerald-700 hover:underline">Aging report →</a>@endcan
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="title">Customers</x-slot>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-bold">Customers <span class="text-sm font-normal text-gray-500">({{ $customers->total() }})</span></h1>
            @can('manage_customers')
                <a href="{{ route('customers.create') }}" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">+ Add customer</a>
            @endcan
        </div>

        <form method="GET" class="bg-white rounded-xl shadow-sm ring-1 ring-gray-100 p-4 flex flex-wrap gap-3">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Search name, phone, email…"
                   class="w-full sm:w-72 rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
            <select name="status" class="rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                <option value="">All statuses</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>
            <button class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">Filter</button>
        </form>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-100 overflow-x-auto">
            <table class="w-full text-sm min-w-[640px]">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-5 py-3">Name</th>
                        <th class="px-5 py-3">Phone</th>
                        <th class="px-5 py-3 text-right">Outstanding</th>
                        <th class="px-5 py-3 text-right">Credit limit</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                @forelse ($customers as $customer)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <a href="{{ route('customers.show', $customer) }}" class="font-medium text-emerald-700 hover:underline">{{ $customer->name }}</a>
                            <div class="text-gray-500 text-xs">{{ $customer->customer_code }} @if($customer->business_name) · {{ $customer->business_name }} @endif</div>
                        </td>
                        <td class="px-5 py-3">{{ $customer->phone }}</td>
                        <td class="px-5 py-3 text-right font-mono {{ $customer->outstandingBalance() > 0 ? 'text-red-600' : '' }}">{{ number_format($customer->outstandingBalance(), 2) }}</td>
                        <td class="px-5 py-3 text-right font-mono">{{ number_format($customer->credit_limit, 2) }}</td>
                        <td class="px-5 py-3"><x-status-badge :status="$customer->status" /></td>
                        <td class="px-5 py-3 text-right whitespace-nowrap">
                            @can('send_messages')<a href="{{ route('messages.compose', $customer) }}" class="text-blue-600 hover:underline text-xs mr-2">Message</a>@endcan
                            @can('manage_customers')<a href="{{ route('customers.edit', $customer) }}" class="text-gray-600 hover:underline text-xs">Edit</a>@endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-gray-500">No customers found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $customers->withQueryString()->links() }}
    </div>
</x-app-layout>

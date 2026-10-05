<x-app-layout>
    <x-slot name="title">Edit customer</x-slot>
    <div class="max-w-2xl">
        <h1 class="text-xl font-bold mb-4">Edit {{ $customer->name }}</h1>
        <form method="POST" action="{{ route('customers.update', $customer) }}" class="bg-white rounded-xl shadow-sm ring-1 ring-gray-100 p-6 space-y-4">
            @csrf @method('PUT')
            @include('customers.form')
            <div class="flex gap-2">
                <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Update customer</button>
                <a href="{{ route('customers.show', $customer) }}" class="rounded-lg px-4 py-2 text-sm font-semibold text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50">Back</a>
            </div>
        </form>
    </div>
</x-app-layout>

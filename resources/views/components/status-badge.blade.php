@props(['status'])
@php
    $map = [
        'paid' => 'bg-green-100 text-green-800', 'completed' => 'bg-green-100 text-green-800',
        'active' => 'bg-green-100 text-green-800', 'sent' => 'bg-blue-100 text-blue-800',
        'delivered' => 'bg-green-100 text-green-800', 'accepted' => 'bg-green-100 text-green-800',
        'draft' => 'bg-gray-100 text-gray-700', 'pending' => 'bg-yellow-100 text-yellow-800',
        'partially_paid' => 'bg-yellow-100 text-yellow-800', 'due_soon' => 'bg-yellow-100 text-yellow-800',
        'open' => 'bg-blue-100 text-blue-800', 'converted' => 'bg-indigo-100 text-indigo-800',
        'overdue' => 'bg-red-100 text-red-800', 'cancelled' => 'bg-red-100 text-red-800',
        'declined' => 'bg-red-100 text-red-800', 'expired' => 'bg-red-100 text-red-800',
        'failed' => 'bg-red-100 text-red-800', 'inactive' => 'bg-gray-200 text-gray-600',
        'written_off' => 'bg-purple-100 text-purple-800', 'disputed' => 'bg-orange-100 text-orange-800',
        'current' => 'bg-sky-100 text-sky-800', 'queued' => 'bg-blue-100 text-blue-800',
        'received' => 'bg-gray-100 text-gray-700', 'skipped_duplicate' => 'bg-gray-200 text-gray-600',
        'human_required' => 'bg-orange-100 text-orange-800', 'automated' => 'bg-indigo-100 text-indigo-800',
        'closed' => 'bg-gray-200 text-gray-600', 'read' => 'bg-green-100 text-green-800',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium '.($map[$status] ?? 'bg-gray-100 text-gray-700')]) }}>
    {{ str($status)->replace('_', ' ')->title() }}
</span>

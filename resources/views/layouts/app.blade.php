<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Biashara') }} — @yield('title', 'Dashboard')</title>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans text-gray-900 antialiased" x-data="{ navOpen: false }">
<div class="min-h-full">
    {{-- Top bar --}}
    <header class="bg-emerald-700 text-white shadow sticky top-0 z-40">
        <div class="mx-auto max-w-7xl px-4 py-3 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <button type="button" @click="navOpen = !navOpen" class="p-2 rounded hover:bg-emerald-600 focus:outline-none focus:ring-2 focus:ring-white/60" aria-label="Toggle navigation">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <a href="{{ route('dashboard') }}" class="font-bold text-lg truncate">{{ \App\Models\Business::current()?->name ?? config('app.name') }}</a>
            </div>
            <div x-data="{ open: false }" class="relative shrink-0">
                <button @click="open = !open" class="flex items-center gap-2 rounded px-2 py-1 hover:bg-emerald-600 focus:outline-none focus:ring-2 focus:ring-white/60" aria-haspopup="true">
                    <span class="hidden sm:inline text-sm">{{ auth()->user()->name }}</span>
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-emerald-500 text-sm font-semibold">{{ substr(auth()->user()->name, 0, 1) }}</span>
                </button>
                <div x-show="open" @click.outside="open = false" x-transition class="absolute right-0 mt-2 w-48 rounded-lg bg-white text-gray-800 shadow-lg ring-1 ring-black/5 py-1 z-50">
                    <div class="px-4 py-2 text-xs text-gray-500 border-b">{{ ucfirst(auth()->user()->role) }}</div>
                    <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm hover:bg-gray-50">My profile</a>
                    @if(auth()->user()->isOwner())
                        <a href="{{ route('users.index') }}" class="block px-4 py-2 text-sm hover:bg-gray-50">Users &amp; permissions</a>
                        <a href="{{ route('audit.index') }}" class="block px-4 py-2 text-sm hover:bg-gray-50">Audit logs</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-50">Sign out</button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <div class="flex">
        {{-- Sidebar / mobile drawer --}}
        <div x-show="navOpen" class="fixed inset-0 z-30 bg-black/40 lg:hidden" @click="navOpen = false" x-transition.opacity></div>
        <aside class="fixed lg:sticky top-[52px] z-30 h-[calc(100vh-52px)] w-64 overflow-y-auto bg-white shadow lg:block transition-transform :translate-x-0"
               :class="navOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
               style="transform: translateX(-100%);" x-cloak>
            <nav class="py-4 px-3 space-y-1 text-sm" aria-label="Main">
                @php $nav = [
                    ['route' => 'dashboard', 'label' => 'Dashboard', 'perm' => 'view_dashboard'],
                    ['route' => 'customers.index', 'label' => 'Customers', 'perm' => 'manage_customers'],
                    ['route' => 'products.index', 'label' => 'Products & Services', 'perm' => 'manage_products'],
                    ['route' => 'quotations.index', 'label' => 'Quotations', 'perm' => 'manage_quotations'],
                    ['route' => 'sales.index', 'label' => 'Sales', 'perm' => 'manage_sales'],
                    ['route' => 'invoices.index', 'label' => 'Invoices', 'perm' => 'view_invoices'],
                    ['route' => 'payments.index', 'label' => 'Payments', 'perm' => 'manage_payments'],
                    ['route' => 'debts.index', 'label' => 'Debts & Reminders', 'perm' => 'view_debts'],
                    ['route' => 'expenses.index', 'label' => 'Expenses', 'perm' => 'manage_expenses'],
                    ['route' => 'messages.index', 'label' => 'WhatsApp & SMS', 'perm' => 'manage_messages'],
                    ['route' => 'reports.profit-loss', 'label' => 'Reports', 'perm' => 'view_reports'],
                    ['route' => 'settings.index', 'label' => 'Settings', 'perm' => null],
                ]; @endphp
                @foreach ($nav as $item)
                    @if ($item['perm'] === null || auth()->user()->can($item['perm']))
                        <a href="{{ route($item['route']) }}"
                           class="block rounded px-3 py-2 {{ request()->routeIs(str_contains($item['route'], '.') ? explode('.', $item['route'])[0].'.*') ? 'bg-emerald-50 text-emerald-800 font-semibold' : 'text-gray-700 hover:bg-gray-50' }}">
                            {{ $item['label'] }}
                        </a>
                    @endif
                @endforeach
                @if (auth()->user()->isOwner())
                    <a href="{{ route('integrations.index') }}" class="block rounded px-3 py-2 {{ request()->routeIs('integrations.*') ? 'bg-emerald-50 text-emerald-800 font-semibold' : 'text-gray-700 hover:bg-gray-50' }}">Integrations</a>
                @endif
            </nav>
        </aside>

        {{-- Main --}}
        <main class="flex-1 min-w-0 px-4 py-6 lg:px-8">
            <x-flash />
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>

<x-app-layout>
    <x-slot name="title">My profile</x-slot>
    <div class="max-w-2xl space-y-6">
        <h1 class="text-xl font-bold">My profile</h1>

        <form method="POST" action="{{ route('profile.update') }}" class="bg-white rounded-xl shadow-sm ring-1 ring-gray-100 p-6 space-y-4">
            @csrf @method('PATCH')
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Name</label>
                    <input name="name" value="{{ old('name', $user->name) }}" required class="mt-1 block w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required class="mt-1 block w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Phone</label>
                    <input name="phone" value="{{ old('phone', $user->phone) }}" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                </div>
                <div>
                    <span class="block text-sm font-medium text-gray-700">Role</span>
                    <p class="mt-1 text-sm text-gray-500">{{ ucfirst($user->role) }}</p>
                </div>
            </div>
            <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Save changes</button>
        </form>

        <form method="POST" action="{{ route('profile.password') }}" class="bg-white rounded-xl shadow-sm ring-1 ring-gray-100 p-6 space-y-4">
            @csrf @method('PUT')
            <h2 class="font-semibold">Change password</h2>
            <div>
                <label class="block text-sm font-medium text-gray-700">Current password</label>
                <input type="password" name="current_password" required autocomplete="current-password" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">New password</label>
                    <input type="password" name="password" required autocomplete="new-password" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Confirm new password</label>
                    <input type="password" name="password_confirmation" required autocomplete="new-password" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                </div>
            </div>
            <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Update password</button>
        </form>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-100 p-6 text-sm text-gray-600">
            <h2 class="font-semibold text-gray-900 mb-2">Account activity</h2>
            <p>Last sign-in: {{ $user->last_login_at?->diffForHumans() ?? 'this session' }} from {{ $user->last_login_ip ?? '—' }}</p>
        </div>
    </div>
</x-app-layout>

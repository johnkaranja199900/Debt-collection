<x-guest-layout>
    <div class="bg-white p-8 rounded-xl shadow-sm ring-1 ring-gray-100">
        <h2 class="text-lg font-semibold mb-6">Sign in to your account</h2>
        <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
            @csrf
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                       class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-base">
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                       class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-base">
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" name="remember" value="1" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                Remember me
            </label>
            <button type="submit" class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                Sign in
            </button>
        </form>
        @if (! \App\Models\User::where('role', 'owner')->exists())
            <p class="mt-4 text-sm text-gray-500 text-center">
                First time here? <a href="{{ route('register') }}" class="font-medium text-emerald-700 hover:underline">Set up your business account</a>.
            </p>
        @endif
    </div>
</x-guest-layout>

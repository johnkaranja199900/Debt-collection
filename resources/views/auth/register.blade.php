<x-guest-layout title="Set up your business">
    <div class="bg-white p-8 rounded-xl shadow-sm ring-1 ring-gray-100">
        <h2 class="text-lg font-semibold mb-1">Create the owner account</h2>
        <p class="text-sm text-gray-500 mb-6">This sets up your business workspace. You will be the owner.</p>
        <form method="POST" action="{{ route('register.store') }}" class="space-y-4">
            @csrf
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Your name</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
            </div>
            <div>
                <label for="business_name" class="block text-sm font-medium text-gray-700">Business name</label>
                <input id="business_name" name="business_name" type="text" value="{{ old('business_name') }}" required class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
            </div>
            <div>
                <label for="phone" class="block text-sm font-medium text-gray-700">Phone (e.g. +2547XXXXXXXX)</label>
                <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" required pattern="[0-9+]{7,20}" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
            </div>
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Password <span class="text-gray-400 font-normal">(min 10 chars, letters + numbers)</span></label>
                <input id="password" name="password" type="password" required class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
            </div>
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
            </div>
            <button type="submit" class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow hover:bg-emerald-700">Create account</button>
        </form>
        <p class="mt-4 text-sm text-gray-500 text-center"><a href="{{ route('login') }}" class="text-emerald-700 hover:underline">Back to sign in</a></p>
    </div>
</x-guest-layout>

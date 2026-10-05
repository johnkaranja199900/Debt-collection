<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

/**
 * First-run registration: the first user becomes the business OWNER and creates
 * the business profile. Further sign-ups are blocked unless an owner invites
 * users (Users & Permissions page) - keeps a single-business installation safe.
 */
class RegisteredUserController extends Controller
{
    public function create(): View
    {
        // Only allow the register screen while no owner exists yet.
        abort_if(User::where('role', User::ROLE_OWNER)->exists(), 403, 'Registration is closed. Ask the owner to add you.');

        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if(User::where('role', User::ROLE_OWNER)->exists(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'business_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+]{7,20}$/'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => $data['password'],
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        Business::firstOrCreate(
            [],
            [
                'name' => $data['business_name'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'currency' => 'KES',
                'timezone' => 'Africa/Nairobi',
            ]
        );

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Welcome! Your business workspace is ready.');
    }
}

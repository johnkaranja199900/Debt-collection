<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Self-service profile + password change. Password changes require the current
 * password (step-up) and are audit logged (spec 47).
 */
class ProfileController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function edit(Request $request): View
    {
        return view('auth.profile', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$request->user()->id],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+]{7,20}$/', 'unique:users,phone,'.$request->user()->id],
        ]);

        $old = $request->user()->only(['name', 'email', 'phone']);
        $request->user()->update($data);
        $this->audit->log('profile_updated', $request->user(), $old, $data);

        return back()->with('success', 'Profile updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        if (! Hash::check($data['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => 'Your current password is incorrect.']);
        }

        $request->user()->update(['password' => $data['password']]);
        $this->audit->log('password_change', $request->user());

        return back()->with('success', 'Password changed.');
    }
}

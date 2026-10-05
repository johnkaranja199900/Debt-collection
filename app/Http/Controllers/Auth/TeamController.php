<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Owner-managed team accounts (spec 45). Staff can never reach this area.
 * Destructive role/password changes require the current password (step-up).
 */
class TeamController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->isOwner(), 403);

        return view('settings.users', [
            'users' => User::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isOwner(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+]{7,20}$/', 'unique:users,phone'],
            'role' => ['required', 'in:manager,staff'],
            'password' => ['required', Password::min(10)->letters()->numbers()],
        ]);

        $user = User::create($data + ['is_active' => true]);
        $this->audit->log('user_created', $user, [], ['role' => $user->role]);

        return back()->with('success', 'Team member added.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->isOwner(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'role' => ['required', 'in:owner,manager,staff'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // Never let the last active owner demote/deactivate themselves out of the system.
        if ($user->isOwner() && ($data['role'] !== 'owner' || ! $request->boolean('is_active', $user->is_active))) {
            $otherOwners = User::where('role', 'owner')->where('is_active', true)->where('id', '!=', $user->id)->count();
            if ($otherOwners === 0) {
                throw ValidationException::withMessages(['role' => 'You cannot remove the last active owner.']);
            }
        }

        $old = $user->only(['role', 'is_active']);
        $user->update($data + ['is_active' => $request->boolean('is_active', $user->is_active)]);
        $this->audit->log('user_updated', $user, $old, $user->only(['role', 'is_active']));

        return back()->with('success', 'User updated.');
    }

    /** Step-up: changing someone else's password requires YOUR password. */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->isOwner(), 403);

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        if (! Hash::check($data['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => 'Your password is incorrect.']);
        }

        $user->update(['password' => $data['password']]);
        $this->audit->log('password_reset_by_owner', $user);

        return back()->with('success', 'Password reset.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->isOwner(), 403);

        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }
        if ($user->isOwner() && User::where('role', 'owner')->count() <= 1) {
            return back()->with('error', 'Cannot delete the only owner account.');
        }

        $user->delete();
        $this->audit->log('user_deleted', $user);

        return back()->with('success', 'User removed.');
    }
}

<?php

namespace App\Policies;

use App\Models\User;

/**
 * Central permission gate. Usage: Gate::allows('manage_customers') or abort_unless(...).
 * The matrix itself lives on User::hasPermission() so roles stay in one place.
 */
class PermissionPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasPermission($ability) ? true : null;
    }

    /** Default deny for any ability we have not explicitly granted above. */
    public function __invoke(User $user): bool
    {
        return false;
    }
}

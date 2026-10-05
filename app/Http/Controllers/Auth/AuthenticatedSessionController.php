<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Session authentication with lockout protection (spec 5/44):
 * - Laravel's RateLimiter throttles brute force per email+IP.
 * - Repeated failures set users.locked_until; successful logins reset counters.
 * - All login activity is audit logged (never passwords).
 */
class AuthenticatedSessionController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember');

        // Throttle: 5 attempts / minute per email+IP (framework-backed).
        if (! $this->ensureIsNotRateLimited($request)) {
            throw ValidationException::withMessages([
                'email' => __('Too many login attempts. Please try again in :seconds seconds.', [
                    'seconds' => method_exists(\Illuminate\Support\Facades\RateLimiter::class, 'availableIn')
                        ? \Illuminate\Support\Facades\RateLimiter::availableIn($this->throttleKey($request))
                        : 60,
                ]),
            ]);
        }

        $user = User::where('email', strtolower(trim($request->string('email'))))->first();

        if ($user && $user->locked_until && $user->locked_until->isFuture()) {
            $this->audit->log('login_locked_account', $user);

            throw ValidationException::withMessages([
                'email' => 'This account is temporarily locked due to repeated failed sign-ins. Try again later.',
            ]);
        }

        if (! Auth::attempt($credentials, $remember)) {
            \Illuminate\Support\Facades\RateLimiter::hit($this->throttleKey($request), 60);

            if ($user) {
                $user->increment('failed_login_attempts');
                if ($user->failed_login_attempts >= 10) {
                    $user->update(['locked_until' => now()->addMinutes(30)]);
                    $this->audit->log('account_locked', $user);
                }
            }
            $this->audit->log('login_failed', $user, extra: ['email' => $request->string('email')->lower()]);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        if (! $user || ! $user->is_active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Your account has been deactivated. Contact the business owner.',
            ]);
        }

        \Illuminate\Support\Facades\RateLimiter::clear($this->throttleKey($request));
        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->saveQuietly();

        $this->audit->log('login', $user);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->audit->log('logout', $request->user());

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function throttleKey(Request $request): string
    {
        return \Illuminate\Support\Str::lower($request->string('email')).'|'.$request->ip();
    }

    private function ensureIsNotRateLimited(Request $request): bool
    {
        return ! \Illuminate\Support\Facades\RateLimiter::tooManyAttempts($this->throttleKey($request), 5);
    }
}

<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\PermissionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Permission-based authorization (spec 45). PermissionPolicy::before()
        // grants an ability when User::hasPermission() matches the role matrix.
        Gate::policy(User::class, PermissionPolicy::class);

        // Force HTTPS in production so cookies/session stay secure end-to-end.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Password::defaults(function () {
            return $this->app->environment('production')
                ? Password::min(10)->letters()->mixedCase()->numbers()->symbols()->uncompromised()
                : Password::min(8);
        });
    }
}

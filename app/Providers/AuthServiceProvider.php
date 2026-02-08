<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Optional: Define custom gates for profile management
        Gate::define('view-profile', function (User $user) {
            return $user->account_status === 'active' && !$user->isLocked();
        });

        Gate::define('update-profile', function (User $user) {
            return $user->account_status === 'active' && !$user->isLocked();
        });
    }
}

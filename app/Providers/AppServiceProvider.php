<?php

namespace App\Providers;

use App\Models\Archive;
use App\Models\Category;
use App\Models\User;
use App\Observers\UserObserver;
use App\Policies\ArchivePolicy;
use App\Policies\CategoryPolicy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use App\Listeners\LoginEventSubscriber;

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
        // Register policies
        Gate::policy(Archive::class, ArchivePolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);

        // Register User observer for auto-tracking
        User::observe(UserObserver::class);

        // Register login event subscriber
        Event::subscribe(LoginEventSubscriber::class);

        // ==================== RATE LIMITING ====================
        // Professional security: Limit authentication attempts
        $this->configureRateLimiting();

        // ==================== CUSTOM AUTH ====================
        // Mid-master: Support login via username OR email
        $this->configureAuthentication();
    }

    /**
     * Configure rate limiting for authentication
     * Professional security: Prevent brute force attacks
     */
    protected function configureRateLimiting(): void
    {
        \Illuminate\Support\Facades\RateLimiter::for('login', function ($request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(5)
                ->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Too many login attempts. Please try again in 1 minute.',
                    ], 429);
                });
        });
    }

    /**
     * Configure custom authentication for username/email login
     * Mid-master professional: Flexible authentication identifier
     */
    protected function configureAuthentication(): void
    {
        // Extend default Eloquent provider to support username OR email
        Auth::provider('flexible-eloquent', function ($app, array $config) {
            return new class($app['hash'], $config['model']) extends \Illuminate\Auth\EloquentUserProvider {
                public function retrieveByCredentials(array $credentials)
                {
                    if (
                        empty($credentials) ||
                        (count($credentials) === 1 &&
                            array_key_exists('password', $credentials))
                    ) {
                        return null;
                    }

                    // Extract identifier (username or email)
                    $identifier = $credentials['email'] ?? $credentials['username'] ?? null;

                    if (!$identifier) {
                        return null;
                    }

                    // Query: username OR email (mid-master flexibility)
                    $query = $this->createModel()->newQuery();

                    return $query->where(function ($q) use ($identifier) {
                        $q->where('email', $identifier)
                            ->orWhere('username', $identifier);
                    })->first();
                }
            };
        });
    }
}

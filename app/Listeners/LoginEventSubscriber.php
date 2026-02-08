<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;

class LoginEventSubscriber
{
    /**
     * Handle user login events.
     * Mid-master security: Session regeneration + activity logging
     */
    public function handleUserLogin(Login $event): void
    {
        /** @var User $user */
        $user = $event->user;

        // Mid-master security: Regenerate session to prevent fixation attacks
        request()->session()->regenerate();

        // Reset failed login attempts
        $user->resetFailedLoginAttempts();

        // Update last login info
        $user->updateLastLogin();

        // Enhanced logging with role information
        $logLevel = $user->isAdmin() ? 'warning' : 'info';
        $logMessage = $user->isAdmin() ? 'Admin/Elevated privilege login' : 'User logged in successfully';

        Log::log($logLevel, $logMessage, [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Handle failed login attempts.
     * Mid-master: Support username OR email identification
     */
    public function handleFailedLogin(Failed $event): void
    {
        // Try to find user by credentials (username OR email)
        $credentials = $event->credentials;
        $identifier = $credentials['email'] ?? $credentials['username'] ?? null;

        // Use User::findForAuth() for flexible lookup
        $user = $identifier ? User::findForAuth($identifier) : null;

        if ($user) {
            // Mid-master security: Increment failed attempts (auto-locks after 5)
            $user->incrementFailedLoginAttempts();

            Log::warning('Failed login attempt', [
                'user_id' => $user->id,
                'email' => $user->email,
                'username' => $user->username,
                'ip_address' => request()->ip(),
                'attempts' => $user->failed_login_attempts,
                'locked' => $user->isLocked(),
            ]);
        } else {
            // Log attempt with non-existent identifier
            Log::warning('Failed login attempt - user not found', [
                'identifier' => $identifier ?? 'unknown',
                'ip_address' => request()->ip(),
            ]);
        }
    }

    /**
     * Handle user logout events.
     */
    public function handleUserLogout(Logout $event): void
    {
        /** @var User|null $user */
        $user = $event->user;

        if ($user) {
            $user->loginActivities()->create([
                'event' => 'logout',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            Log::info('User logged out', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
        }
    }

    /**
     * Handle user registration events.
     */
    public function handleUserRegistration(Registered $event): void
    {
        /** @var User $user */
        $user = $event->user;

        $user->loginActivities()->create([
            'event' => 'registration',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        Log::info('New user registered', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'handleUserLogin',
            Failed::class => 'handleFailedLogin',
            Logout::class => 'handleUserLogout',
            Registered::class => 'handleUserRegistration',
        ];
    }
}

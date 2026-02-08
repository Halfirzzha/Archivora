<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Professional Mid-Max Middleware: Check Account Status & Update Realtime Last Seen
 * 
 * Responsibilities:
 * - Block locked/blocked/inactive accounts
 * - Update last_seen_at/ip realtime with debouncing (1 minute)
 * - Log security events for audit trail
 */
class CheckAccountStatus
{
    /**
     * Handle an incoming request.
     * Professional mid-max: Strict type hints, null-safe operators
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        // Skip if user is not authenticated
        if (!$user) {
            return $next($request);
        }

        // Professional: Update last_seen only if different IP or >60 seconds since last update (prevent spam)
        $shouldUpdate = $user->last_seen_ip !== $request->ip()
            || $user->last_seen_at?->diffInSeconds(now()) > 60;

        if ($shouldUpdate) {
            $user->updateQuietly([
                'last_seen_ip' => $request->ip(),
                'last_seen_at' => now(), // Real-time activity tracking
            ]);
        }

        // Security Check 1: Account locked (temporary - 30 minutes)
        if ($user->isLocked()) {
            $minutesRemaining = 30 - ($user->locked_at?->diffInMinutes(now()) ?? 0);

            // Log locked account access attempt
            $user->logActivity('access_denied_locked', [
                'context' => ['minutes_remaining' => $minutesRemaining],
            ]);

            Auth::logout();

            return redirect()->route('filament.core-gateway.auth.login')->withErrors([
                'data.email' => "Account Anda terkunci sementara karena kesalahan login berulang. Silakan coba lagi dalam {$minutesRemaining} menit."
            ]);
        }

        // Security Check 2: Account blocked (permanent or temporary)
        if ($user->isBlocked()) {
            $message = 'Account Anda telah diblokir.';

            if ($user->blocked_reason) {
                $message .= ' Alasan: ' . $user->blocked_reason;
            }

            // Log blocked account access attempt
            $user->logActivity('access_denied_blocked', [
                'context' => [
                    'reason' => $user->blocked_reason,
                    'blocked_until' => $user->blocked_until?->toDateTimeString(),
                ],
            ]);

            if ($user->blocked_until) {
                $message .= ' Diblokir hingga: ' . $user->blocked_until->format('d M Y H:i');
            }

            Auth::logout();

            return redirect()->route('filament.core-gateway.auth.login')->withErrors([
                'data.email' => $message
            ]);
        }

        // Security Check 3: Account must be active
        if ($user->account_status !== 'active') {
            Auth::logout();

            return redirect()->route('filament.core-gateway.auth.login')->withErrors([
                'data.email' => 'Account status Anda: ' . $user->account_status . '. Hubungi administrator.'
            ]);
        }

        return $next($request);
    }
}

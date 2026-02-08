<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UserObserver
{
    /**
     * Handle the User "creating" event.
     */
    public function creating(User $user): void
    {
        // Set default account status if not provided
        if (empty($user->account_status)) {
            $user->account_status = 'active';
        }

        // Set default failed login attempts
        if (is_null($user->failed_login_attempts)) {
            $user->failed_login_attempts = 0;
        }
    }

    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        Log::info('New user registered', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * Handle the User "updating" event.
     */
    public function updating(User $user): void
    {
        $currentUserId = Auth::id() ?? 'system';

        // Log critical changes
        if ($user->isDirty('account_status')) {
            Log::warning('User account status changed', [
                'user_id' => $user->id,
                'from' => $user->getOriginal('account_status'),
                'to' => $user->account_status,
                'changed_by' => $currentUserId,
            ]);
        }

        // Log role changes - critical security event
        if ($user->isDirty('role')) {
            Log::warning('User role changed', [
                'user_id' => $user->id,
                'email' => $user->email,
                'from_role' => $user->getOriginal('role'),
                'to_role' => $user->role,
                'changed_by' => $currentUserId,
                'ip_address' => request()->ip(),
            ]);
        }

        // Log blocked status
        if ($user->isDirty('blocked_until')) {
            Log::warning('User blocked', [
                'user_id' => $user->id,
                'blocked_until' => $user->blocked_until,
                'blocked_by' => $user->blocked_by,
                'reason' => $user->blocked_reason,
            ]);
        }
    }

    /**
     * Handle the User "deleting" event (soft delete).
     * Clean up avatar file from local disk (not remote/immutable storage).
     */
    public function deleting(User $user): void
    {
        // Delete avatar from local disk if exists
        if ($user->avatar && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->avatar)) {
            try {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($user->avatar);
                Log::info('Avatar deleted with user', [
                    'user_id' => $user->id,
                    'avatar' => $user->avatar,
                    'deleted_by' => Auth::id() ?? 'system',
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to delete avatar on user deletion', [
                    'user_id' => $user->id,
                    'avatar' => $user->avatar,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::warning('User deleted (soft delete)', [
            'user_id' => $user->id,
            'email' => $user->email,
            'deleted_by' => Auth::id() ?? 'system',
        ]);
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        Log::info('User restored', [
            'user_id' => $user->id,
            'restored_by' => Auth::id() ?? 'system',
        ]);
    }
}

<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Only admin/developer can view user list
        return in_array($user->role, ['admin', 'developer']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        // User can view their own profile, or admin/developer can view anyone
        return $user->id === $model->id || in_array($user->role, ['admin', 'developer']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only admin/developer can create users
        return in_array($user->role, ['admin', 'developer']);
    }

    /**
     * Determine whether the user can update the model.
     * CRITICAL: This is what Profile.php checks!
     */
    public function update(User $user, User $model): bool
    {
        // Security checks first
        if ($user->account_status !== 'active') {
            return false; // Blocked/suspended users cannot update
        }

        if ($user->locked_at && $user->isLocked()) {
            return false; // Locked accounts cannot update
        }

        // Self-edit only OR admin/developer can edit anyone
        if ($user->id === $model->id) {
            return true; // User can update their own profile
        }

        // Admin/developer can edit other users
        return in_array($user->role, ['admin', 'developer']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        // Cannot delete yourself
        if ($user->id === $model->id) {
            return false;
        }

        // Only admin/developer can delete
        return in_array($user->role, ['admin', 'developer']);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return in_array($user->role, ['admin', 'developer']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        // Only developer can force delete
        return $user->role === 'developer';
    }
}

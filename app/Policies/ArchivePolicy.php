<?php

namespace App\Policies;

use App\Models\Archive;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ArchivePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view archive list
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Archive $archive): bool
    {
        // Users can view if:
        // 1. Archive is not confidential, OR
        // 2. User is the uploader, OR
        // 3. User is admin (implement role check here if needed)
        if (!$archive->is_confidential) {
            return true;
        }

        return $archive->uploaded_by === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true; // All authenticated users can create archives
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Archive $archive): bool
    {
        // Only uploader can update their own archives
        return $archive->uploaded_by === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Archive $archive): bool
    {
        // Only uploader can delete (soft delete) their archives
        return $archive->uploaded_by === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Archive $archive): bool
    {
        return $archive->uploaded_by === $user->id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Archive $archive): bool
    {
        // Only admin can force delete (implement admin check)
        return false; // Disabled for safety
    }

    /**
     * Custom policy for downloading archives
     */
    public function download(User $user, Archive $archive): bool
    {
        // Same logic as view - log this in activity
        return $this->view($user, $archive);
    }
}

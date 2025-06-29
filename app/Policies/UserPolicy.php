<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('admin.user_management.view') || $user->is_developer;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        // Users can view themselves OR admins can view others
        return $user->id === $model->id || 
               $user->hasPermission('admin.user_management.view') || 
               $user->is_developer;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('admin.user_management.create') || $user->is_developer;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        // Users can update themselves OR admins can update others
        if ($user->id === $model->id) {
            return true;
        }
        
        return $user->hasPermission('admin.user_management.edit') || $user->is_developer;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        // Users cannot delete themselves, only admins can delete
        return $user->id !== $model->id && 
               ($user->hasPermission('admin.user_management.delete') || $user->is_developer);
    }

    /**
     * Determine whether the user can delete the model in bulk.
     */
    public function deleteAny(User $user, User $model): bool
    {
        return $user->hasPermission('admin.user_management.delete') || $user->is_developer;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return $user->hasPermission('admin.user_management.edit') || $user->is_developer;
    }

    /**
     * Determine whether the user can restore the model in bulk.
     */
    public function restoreAny(User $user, User $model): bool
    {
        return $user->hasPermission('admin.user_management.edit') || $user->is_developer;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return false; // Force delete should never be allowed
    }

    /**
     * Determine whether the user can permanently delete the model in bulk.
     */
    public function forceDeleteAny(User $user, User $model): bool
    {
        return false; // Force delete should never be allowed
    }

    /**
     * Determine whether the user can grant permissions to other users.
     */
    public function grantPermissions(User $user): bool 
    {
        return $user->hasPermission('admin.user_management.permissions') || $user->is_developer;
    }

    /**
     * Determine whether the user can revoke permissions from other users.
     */
    public function revokePermissions(User $user): bool
    {
        return $user->hasPermission('admin.user_management.permissions') || $user->is_developer;
    }

    /**
     * Determine whether the user can view user permissions.
     */
    public function viewPermissions(User $user, User $model): bool
    {
        return $user->id === $model->id || 
               $user->hasPermission('admin.user_management.view') || 
               $user->is_developer;
    }
}

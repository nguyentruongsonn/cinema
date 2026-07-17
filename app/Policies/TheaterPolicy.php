<?php

namespace App\Policies;

use App\Models\Theater;
use App\Models\User;

class TheaterPolicy
{
    /**
     * Determine if the user can view any theaters.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the theater.
     */
    public function view(?User $user, Theater $theater): bool
    {
        return true;
    }

    /**
     * Determine if the user can create theaters.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']) || $user->hasPermission('create_theaters');
    }

    /**
     * Determine if the user can update the theater.
     */
    public function update(User $user, Theater $theater): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']) || $user->hasPermission('edit_theaters');
    }

    /**
     * Determine if the user can delete the theater.
     */
    public function delete(User $user, Theater $theater): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']) || $user->hasPermission('delete_theaters');
    }
}

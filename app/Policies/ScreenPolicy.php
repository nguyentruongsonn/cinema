<?php

namespace App\Policies;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ScreenPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view any screens.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']) || $user->hasPermission('view_screens');
    }

    /**
     * Determine if the user can view the screen.
     */
    public function view(User $user, Screen $screen): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']) || $user->hasPermission('view_screens');
    }

    /**
     * Determine if the user can create screens.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']) || $user->hasPermission('create_screens');
    }

    /**
     * Determine if the user can update the screen.
     */
    public function update(User $user, Screen $screen): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']) || $user->hasPermission('edit_screens');
    }

    /**
     * Determine if the user can delete the screen.
     */
    public function delete(User $user, Screen $screen): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']) || $user->hasPermission('delete_screens');
    }

    /**
     * Determine if the user can manage screen seats.
     */
    public function manageSeats(User $user, Screen $screen): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']) || $user->hasPermission('manage_seat_layouts');
    }

    /**
     * Determine if the user can toggle screen status.
     */
    public function toggleStatus(User $user, Screen $screen): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']) || $user->hasPermission('edit_screens');
    }
}

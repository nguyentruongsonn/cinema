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
        return $user->hasPermission('screens.view');
    }

    /**
     * Determine if the user can view the screen.
     */
    public function view(User $user, Screen $screen): bool
    {
        return $user->hasPermission('screens.view') && $this->canAccessScreenTheater($user, $screen);
    }

    /**
     * Determine if the user can create screens.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('screens.create');
    }

    /**
     * Determine if the user can update the screen.
     */
    public function update(User $user, Screen $screen): bool
    {
        return $user->hasPermission('screens.update') && $this->canAccessScreenTheater($user, $screen);
    }

    /**
     * Determine if the user can delete the screen.
     */
    public function delete(User $user, Screen $screen): bool
    {
        return $user->hasPermission('screens.delete') && $this->canAccessScreenTheater($user, $screen);
    }

    /**
     * Determine if the user can manage screen seats.
     */
    public function manageSeats(User $user, Screen $screen): bool
    {
        return $user->hasPermission('screens.manage_seats') && $this->canAccessScreenTheater($user, $screen);
    }

    /**
     * Determine if the user can toggle screen status.
     */
    public function toggleStatus(User $user, Screen $screen): bool
    {
        return $user->hasPermission('screens.update') && $this->canAccessScreenTheater($user, $screen);
    }

    private function canAccessScreenTheater(User $user, Screen $screen): bool
    {
        return ! $user->requiresTheaterScope()
            || $user->isAssignedToTheater((int) $screen->theater_id);
    }
}

<?php

namespace App\Policies;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ScreenPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('screens.view');
    }

    public function view(User $user, Screen $screen): bool
    {
        return $user->hasPermission('screens.view') && $this->canAccessScreenTheater($user, $screen);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('screens.create');
    }

    public function update(User $user, Screen $screen): bool
    {
        return $user->hasPermission('screens.update') && $this->canAccessScreenTheater($user, $screen);
    }

    public function delete(User $user, Screen $screen): bool
    {
        return $user->hasPermission('screens.delete') && $this->canAccessScreenTheater($user, $screen);
    }

    public function manageSeats(User $user, Screen $screen): bool
    {
        return $user->hasPermission('screens.manage_seats') && $this->canAccessScreenTheater($user, $screen);
    }

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

<?php

namespace App\Policies;

use App\Models\Screen;
use App\Models\Showtime;
use App\Models\User;

class ShowtimePolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Showtime $showtime): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('showtimes.create');
    }

    public function update(User $user, Showtime $showtime): bool
    {
        return $user->hasPermission('showtimes.update') && $this->canAccessShowtimeTheater($user, $showtime);
    }

    public function delete(User $user, Showtime $showtime): bool
    {
        return $user->hasPermission('showtimes.delete') && $this->canAccessShowtimeTheater($user, $showtime);
    }

    public function bulkCreate(User $user): bool
    {
        return $user->hasPermission('showtimes.bulk_create') || $user->hasPermission('showtimes.create');
    }

    private function canAccessShowtimeTheater(User $user, Showtime $showtime): bool
    {
        if (! $user->requiresTheaterScope()) {
            return true;
        }

        if (! $showtime->relationLoaded('screen')) {
            $showtime->load('screen:id,theater_id');
        }

        $screen = $showtime->screen;

        return $screen instanceof Screen
            && $user->isAssignedToTheater((int) $screen->theater_id);
    }
}

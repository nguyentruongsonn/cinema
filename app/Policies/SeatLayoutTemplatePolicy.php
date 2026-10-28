<?php

namespace App\Policies;

use App\Models\SeatLayoutTemplate;
use App\Models\User;

class SeatLayoutTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['seat_layouts.view', 'screens.manage_seats']);
    }

    public function view(User $user, SeatLayoutTemplate $template): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyPermission(['seat_layouts.create', 'screens.manage_seats']);
    }

    public function update(User $user, SeatLayoutTemplate $template): bool
    {
        return $user->hasAnyPermission(['seat_layouts.update', 'screens.manage_seats']);
    }

    public function delete(User $user, SeatLayoutTemplate $template): bool
    {
        return $user->hasAnyPermission(['seat_layouts.delete', 'screens.manage_seats']);
    }
}

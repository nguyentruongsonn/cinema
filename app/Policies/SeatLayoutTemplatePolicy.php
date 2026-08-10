<?php

namespace App\Policies;

use App\Models\SeatLayoutTemplate;
use App\Models\User;

class SeatLayoutTemplatePolicy
{
    /**
     * Determine if user can view any seat layout templates.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['seat_layouts.view', 'screens.manage_seats']);
    }

    /**
     * Determine if user can view a specific seat layout template.
     */
    public function view(User $user, SeatLayoutTemplate $template): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine if user can create seat layout templates.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyPermission(['seat_layouts.create', 'screens.manage_seats']);
    }

    /**
     * Determine if user can update seat layout templates.
     */
    public function update(User $user, SeatLayoutTemplate $template): bool
    {
        return $user->hasAnyPermission(['seat_layouts.update', 'screens.manage_seats']);
    }

    /**
     * Determine if user can delete seat layout templates.
     */
    public function delete(User $user, SeatLayoutTemplate $template): bool
    {
        return $user->hasAnyPermission(['seat_layouts.delete', 'screens.manage_seats']);
    }
}

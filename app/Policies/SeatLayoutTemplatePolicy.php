<?php

namespace App\Policies;

use App\Models\SeatLayoutTemplate;
use App\Models\User;

class SeatLayoutTemplatePolicy
{
    /**
     * Determine if user can view any seat layout templates.
     */
    public function viewAny(?User $user): bool
    {
        // Public read access for listing templates
        return true;
    }

    /**
     * Determine if user can view a specific seat layout template.
     */
    public function view(?User $user, SeatLayoutTemplate $template): bool
    {
        // Public read access for individual templates
        return true;
    }

    /**
     * Determine if user can create seat layout templates.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']) || $user->hasPermission('manage_seat_layouts');
    }

    /**
     * Determine if user can update seat layout templates.
     */
    public function update(User $user, SeatLayoutTemplate $template): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']) || $user->hasPermission('manage_seat_layouts');
    }

    /**
     * Determine if user can delete seat layout templates.
     */
    public function delete(User $user, SeatLayoutTemplate $template): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']) || $user->hasPermission('manage_seat_layouts');
    }
}

<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Showtime;

class ShowtimePolicy
{
    /**
     * Determine if user can view any showtimes.
     *
     * Public endpoint - anyone can list showtimes.
     * Admin stats/management requires permission.
     */
    public function viewAny(?User $user): bool
    {
        // Public can view showtimes
        return true;
    }

    /**
     * Determine if user can view the showtime.
     *
     * Public endpoint - anyone can view showtime details.
     */
    public function view(?User $user, Showtime $showtime): bool
    {
        // Public can view individual showtimes
        return true;
    }

    /**
     * Determine if user can create showtimes.
     *
     * Only admin/staff with showtime management permission.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']) || $user->hasPermission('create_showtimes');
    }

    /**
     * Determine if user can update the showtime.
     *
     * Only admin/staff with showtime management permission.
     * Additional business rules (e.g., has bookings) should be in service layer.
     */
    public function update(User $user, Showtime $showtime): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']) || $user->hasPermission('edit_showtimes');
    }

    /**
     * Determine if user can delete the showtime.
     *
     * Only admin/staff with showtime management permission.
     * Additional business rules (e.g., has bookings) should be in service layer.
     */
    public function delete(User $user, Showtime $showtime): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']) || $user->hasPermission('delete_showtimes');
    }

    /**
     * Determine if user can bulk create showtimes.
     *
     * Only admin/staff with showtime management permission.
     * Bulk operations are high-impact administrative tasks.
     */
    public function bulkCreate(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']) || $user->hasPermission('create_showtimes');
    }
}

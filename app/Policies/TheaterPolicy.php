<?php

namespace App\Policies;

use App\Models\Theater;
use App\Models\User;

class TheaterPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Theater $theater): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('theaters.create');
    }

    public function update(User $user, Theater $theater): bool
    {
        return $user->hasPermission('theaters.update')
            && (! $user->requiresTheaterScope() || $user->isAssignedToTheater((int) $theater->id));
    }

    public function delete(User $user, Theater $theater): bool
    {
        return $user->hasPermission('theaters.delete')
            && (! $user->requiresTheaterScope() || $user->isAssignedToTheater((int) $theater->id));
    }
}

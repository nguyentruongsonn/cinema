<?php

namespace App\Policies;

use App\Models\Movie;
use App\Models\User;

class MoviePolicy
{
    public function viewAny(?User $user = null): bool
    {
        return true;
    }

    public function view(?User $user, Movie $movie): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('movies.create');
    }

    public function update(User $user, Movie $movie): bool
    {
        return $user->hasPermission('movies.update');
    }

    public function delete(User $user, Movie $movie): bool
    {
        return $user->hasPermission('movies.delete');
    }

    public function toggleStatus(User $user, Movie $movie): bool
    {
        return $user->hasPermission('movies.update');
    }

    public function toggleHot(User $user, Movie $movie): bool
    {
        return $user->hasPermission('movies.update');
    }
}

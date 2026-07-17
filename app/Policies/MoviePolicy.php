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
        return $user->hasAnyRole(['admin', 'super-admin']) || $user->hasPermission('create_movies');
    }

    public function update(User $user, Movie $movie): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']) || $user->hasPermission('edit_movies');
    }

    public function delete(User $user, Movie $movie): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']) || $user->hasPermission('delete_movies');
    }

    public function toggleStatus(User $user, Movie $movie): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']) || $user->hasPermission('edit_movies');
    }

    public function toggleHot(User $user, Movie $movie): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']) || $user->hasPermission('edit_movies');
    }
}

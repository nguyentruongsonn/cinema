<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('branches.view');
    }

    public function view(User $user, Branch $branch): bool
    {
        return $user->hasPermission('branches.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('branches.create');
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->hasPermission('branches.update');
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $user->hasPermission('branches.delete');
    }

    public function toggleActive(User $user, Branch $branch): bool
    {
        return $user->hasPermission('branches.update');
    }
}

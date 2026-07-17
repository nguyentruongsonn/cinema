<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    /**
     * Determine if the user can view any branches.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    /**
     * Determine if the user can view the branch.
     */
    public function view(User $user, Branch $branch): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    /**
     * Determine if the user can create branches.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    /**
     * Determine if the user can update the branch.
     */
    public function update(User $user, Branch $branch): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    /**
     * Determine if the user can delete the branch.
     */
    public function delete(User $user, Branch $branch): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    /**
     * Determine if the user can toggle branch active status.
     */
    public function toggleActive(User $user, Branch $branch): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }
}

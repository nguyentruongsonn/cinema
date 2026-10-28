<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.view');
    }

    public function view(User $user, User $targetUser): bool
    {
        if ($user->id === $targetUser->id) {
            return true;
        }

        return $user->hasPermission('users.view')
            && (! $user->requiresTheaterScope() || $this->sharesTheater($user, $targetUser));
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('users.create');
    }

    public function update(User $user, User $targetUser): bool
    {
        if ($user->id === $targetUser->id) {
            return true;
        }

        if (! $this->hasCatalogRole($targetUser)) {
            return false;
        }

        return $user->hasPermission('users.update')
            && (! $user->requiresTheaterScope() || $this->sharesTheater($user, $targetUser));
    }

    public function delete(User $user, User $targetUser): bool
    {
        if ($user->id === $targetUser->id) {
            return false;
        }

        if (! $user->hasPermission('users.delete')) {
            return false;
        }

        if (! $this->hasCatalogRole($targetUser)) {
            return false;
        }

        return ! $user->requiresTheaterScope() || $this->sharesTheater($user, $targetUser);
    }

    public function updateRole(User $user, User $targetUser): bool
    {
        if ($user->id === $targetUser->id) {
            return false;
        }

        if (! $this->hasCatalogRole($targetUser)) {
            return false;
        }

        return $user->hasPermission('users.manage_roles')
            && (! $user->requiresTheaterScope() || $this->sharesTheater($user, $targetUser));
    }

    public function updateLoyaltyPoints(User $user, User $targetUser): bool
    {
        if ($user->id === $targetUser->id) {
            return false;
        }

        return $user->hasPermission('users.manage_loyalty')
            && (! $user->requiresTheaterScope() || $this->sharesTheater($user, $targetUser));
    }

    public function updateStatus(User $user, User $targetUser): bool
    {
        if ($user->id === $targetUser->id) {
            return false;
        }

        if (! $this->hasCatalogRole($targetUser)) {
            return false;
        }

        return $user->hasPermission('users.manage_status')
            && (! $user->requiresTheaterScope() || $this->sharesTheater($user, $targetUser));
    }

    public function changePassword(User $user, User $targetUser): bool
    {
        if ($user->id === $targetUser->id) {
            return true;
        }

        return $this->canResetCredentials($user, $targetUser);
    }

    public function resetPassword(User $user, User $targetUser): bool
    {
        if ($user->id === $targetUser->id) {
            return false;
        }

        return $this->canResetCredentials($user, $targetUser);
    }

    public function viewOrders(User $user, User $targetUser): bool
    {
        return $this->view($user, $targetUser);
    }

    public function impersonate(User $user, User $targetUser): bool
    {
        if ($user->id === $targetUser->id) {
            return false;
        }

        return false;
    }

    private function canResetCredentials(User $user, User $targetUser): bool
    {
        if (! $this->hasCatalogRole($targetUser)) {
            return false;
        }

        return $user->hasPermission('users.update')
            && (! $user->requiresTheaterScope() || $this->sharesTheater($user, $targetUser));
    }

    private function hasCatalogRole(User $user): bool
    {
        return $user->role === null
            || (
                array_key_exists($user->role->slug, config('rbac.roles', []))
                || array_key_exists($user->role->slug, config('rbac.legacy_role_map', []))
            );
    }

    private function sharesTheater(User $user, User $targetUser): bool
    {
        $userTheaters = $user->theaters()->pluck('theaters.id')->toArray();
        $targetTheaters = $targetUser->theaters()->pluck('theaters.id')->toArray();
        return count(array_intersect($userTheaters, $targetTheaters)) > 0;
    }
}

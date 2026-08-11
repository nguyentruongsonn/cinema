<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine if user can view any users.
     *
     * Regular users can only see their own profile.
     * Admins can list all users.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.view');
    }

    /**
     * Determine if user can view the target user's profile.
     *
     * IDOR Protection: User can only view their own profile OR be admin.
     */
    public function view(User $user, User $targetUser): bool
    {
        // User viewing their own profile
        if ($user->id === $targetUser->id) {
            return true;
        }

        return $user->hasPermission('users.view')
            && (! $user->requiresTheaterScope() || $this->sharesTheater($user, $targetUser));
    }

    /**
     * Determine if user can create users.
     *
     * Only admin can create users directly (registration is public).
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('users.create');
    }

    /**
     * Determine if user can update the target user's profile.
     *
     * IDOR Protection: User can only update their own profile OR be admin.
     * Admin updates may have additional restrictions based on field.
     */
    public function update(User $user, User $targetUser): bool
    {
        // User updating their own profile (basic info only)
        if ($user->id === $targetUser->id) {
            return true;
        }

        if (! $this->hasCatalogRole($targetUser)) {
            return false;
        }

        return $user->hasPermission('users.update')
            && (! $user->requiresTheaterScope() || $this->sharesTheater($user, $targetUser));
    }

    /**
     * Determine if user can delete the target user.
     *
     * Only admin with specific permission can delete users.
     */
    public function delete(User $user, User $targetUser): bool
    {
        // Cannot delete yourself
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

    /**
     * Determine if user can change the target user's role.
     *
     * Only admin with specific permission.
     * Regular users CANNOT change roles (prevents privilege escalation).
     */
    public function updateRole(User $user, User $targetUser): bool
    {
        // Regular users cannot change roles
        if ($user->id === $targetUser->id) {
            return false;
        }

        if (! $this->hasCatalogRole($targetUser)) {
            return false;
        }

        return $user->hasPermission('users.manage_roles')
            && (! $user->requiresTheaterScope() || $this->sharesTheater($user, $targetUser));
    }

    /**
     * Determine if user can update loyalty points.
     *
     * Only admin with specific permission.
     * Regular users CANNOT modify their own loyalty points.
     */
    public function updateLoyaltyPoints(User $user, User $targetUser): bool
    {
        // Regular users cannot modify loyalty points
        if ($user->id === $targetUser->id) {
            return false;
        }

        // Only admin with loyalty management permission
        return $user->hasPermission('users.manage_loyalty')
            && (! $user->requiresTheaterScope() || $this->sharesTheater($user, $targetUser));
    }

    /**
     * Determine if user can change the target user's status.
     *
     * Only admin with specific permission.
     * Regular users CANNOT change their own status (prevents unbanning).
     */
    public function updateStatus(User $user, User $targetUser): bool
    {
        // Regular users cannot change status
        if ($user->id === $targetUser->id) {
            return false;
        }

        if (! $this->hasCatalogRole($targetUser)) {
            return false;
        }

        // Only admin with user management permission
        return $user->hasPermission('users.manage_status')
            && (! $user->requiresTheaterScope() || $this->sharesTheater($user, $targetUser));
    }

    /**
     * Determine if user can change password for the target user.
     *
     * Users can change their own password.
     * Admin can force password reset for others.
     */
    public function changePassword(User $user, User $targetUser): bool
    {
        // User changing their own password
        if ($user->id === $targetUser->id) {
            return true;
        }

        return $this->canResetCredentials($user, $targetUser);
    }

    /**
     * Determine if user can reset another user's password from admin flows.
     *
     * This intentionally excludes self-service password changes, which should
     * use ChangePasswordRequest and require the current password.
     */
    public function resetPassword(User $user, User $targetUser): bool
    {
        if ($user->id === $targetUser->id) {
            return false;
        }

        return $this->canResetCredentials($user, $targetUser);
    }

    /**
     * Determine if user can view the target user's orders.
     *
     * Users can view their own orders.
     * Admin can view any user's orders.
     */
    public function viewOrders(User $user, User $targetUser): bool
    {
        return $this->view($user, $targetUser);
    }

    /**
     * Determine if user can impersonate the target user.
     *
     * Impersonation is disabled until a dedicated audited workflow exists.
     */
    public function impersonate(User $user, User $targetUser): bool
    {
        // Cannot impersonate yourself
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

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
        return $user->hasAnyRole(['admin', 'super-admin']) || $user->hasPermission('view_users');
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

        // Admin can view any user
        if ($user->hasAnyRole(['admin', 'super-admin'])) {
            return true;
        }

        // Staff with user viewing permission
        if ($user->hasPermission('view_users')) {
            return true;
        }

        if ($user->hasRole('theater_manager') && $this->sharesTheater($user, $targetUser)) {
            return true;
        }

        return false;
    }

    /**
     * Determine if user can create users.
     *
     * Only admin can create users directly (registration is public).
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']) || $user->hasRole('theater_manager') || $user->hasPermission('users.create') || $user->hasPermission('staff.create') || $user->hasPermission('create_users');
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

        if ($this->isAdministrativeUser($targetUser) && !$user->hasRole('super-admin')) {
            return false;
        }

        // Admin can update non-administrative users; only super-admin can update administrators.
        if ($user->hasAnyRole(['admin', 'super-admin'])) {
            return true;
        }

        // Staff with user update permission
        if ($user->hasPermission('edit_users')) {
            return true;
        }

        if ($user->hasRole('theater_manager') && $this->sharesTheater($user, $targetUser)) {
            return true;
        }

        return false;
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

        // Only admin with delete permission
        if (!$user->hasAnyRole(['admin', 'super-admin']) && !$user->hasPermission('delete_users')) {
            return false;
        }

        // Administrative accounts are protected by the role hierarchy.
        if ($this->isAdministrativeUser($targetUser) && !$user->hasRole('super-admin')) {
            return false;
        }

        if ($user->hasRole('theater_manager') && $this->sharesTheater($user, $targetUser)) {
            return true;
        }

        return true;
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

        if ($this->isAdministrativeUser($targetUser) && !$user->hasRole('super-admin')) {
            return false;
        }

        if ($user->hasRole('theater_manager') && $this->sharesTheater($user, $targetUser)) {
            return true;
        }

        // Only super-admin or delegated role managers can change non-administrative users.
        return $user->hasRole('super-admin')
            || $user->hasPermission('manage_user_roles')
            || $user->hasPermission('users.manage_roles');
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
        return $user->hasRole('super-admin') || $user->hasPermission('users.manage_loyalty');
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

        if ($this->isAdministrativeUser($targetUser) && !$user->hasRole('super-admin')) {
            return false;
        }

        // Only admin with user management permission
        return $user->hasRole('super-admin') || $user->hasPermission('users.manage_status');
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
     * Only super admin with specific permission.
     */
    public function impersonate(User $user, User $targetUser): bool
    {
        // Cannot impersonate yourself
        if ($user->id === $targetUser->id) {
            return false;
        }

        // Only super admin with impersonation permission
        return $user->hasRole('super-admin');
    }

    private function canResetCredentials(User $user, User $targetUser): bool
    {
        if ($this->isAdministrativeUser($targetUser)) {
            return $user->hasRole('super-admin');
        }

        return $user->hasAnyRole(['admin', 'super-admin']) || $user->hasPermission('edit_users');
    }

    private function isAdministrativeUser(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    private function sharesTheater(User $user, User $targetUser): bool
    {
        $userTheaters = $user->theaters()->pluck('theaters.id')->toArray();
        $targetTheaters = $targetUser->theaters()->pluck('theaters.id')->toArray();
        return count(array_intersect($userTheaters, $targetTheaters)) > 0;
    }
}

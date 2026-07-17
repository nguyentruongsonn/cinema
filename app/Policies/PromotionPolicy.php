<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Promotion;

class PromotionPolicy
{
    /**
     * Determine if user can view any promotions.
     *
     * For now, treat as admin-only endpoint.
     * If promotions become public, adjust accordingly.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']) || $user->hasPermission('view_promotions');
    }

    /**
     * Determine if user can view the promotion.
     *
     * Admin-only for now.
     */
    public function view(User $user, Promotion $promotion): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']) || $user->hasPermission('view_promotions');
    }

    /**
     * Determine if user can create promotions.
     *
     * Only admin/staff with promotion management permission.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']) || $user->hasPermission('create_promotions');
    }

    /**
     * Determine if user can update the promotion.
     *
     * Only admin/staff with promotion management permission.
     * Additional business rules (e.g., used promotions) should be in service layer.
     */
    public function update(User $user, Promotion $promotion): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']) || $user->hasPermission('edit_promotions');
    }

    /**
     * Determine if user can delete the promotion.
     *
     * Only admin/staff with promotion management permission.
     * Additional business rules (e.g., usage_count > 0) should be in service layer.
     */
    public function delete(User $user, Promotion $promotion): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']) || $user->hasPermission('delete_promotions');
    }

    /**
     * Determine if user can toggle promotion status.
     *
     * Only admin/staff with promotion management permission.
     */
    public function toggleStatus(User $user, Promotion $promotion): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']) || $user->hasPermission('edit_promotions');
    }

    /**
     * Determine if user can reset usage count.
     *
     * CRITICAL PERMISSION: Resetting usage count affects revenue integrity.
     * Require explicit permission beyond normal update rights.
     */
    public function resetUsageCount(User $user, Promotion $promotion): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }
}

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
        return $user->hasPermission('promotions.view');
    }

    /**
     * Determine if user can view the promotion.
     *
     * Admin-only for now.
     */
    public function view(User $user, Promotion $promotion): bool
    {
        return $user->hasPermission('promotions.view');
    }

    /**
     * Determine if user can create promotions.
     *
     * Only admin/staff with promotion management permission.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('promotions.create');
    }

    /**
     * Determine if user can update the promotion.
     *
     * Only admin/staff with promotion management permission.
     * Additional business rules (e.g., used promotions) should be in service layer.
     */
    public function update(User $user, Promotion $promotion): bool
    {
        return $user->hasPermission('promotions.update');
    }

    /**
     * Determine if user can delete the promotion.
     *
     * Only admin/staff with promotion management permission.
     * Additional business rules (e.g., usage_count > 0) should be in service layer.
     */
    public function delete(User $user, Promotion $promotion): bool
    {
        return $user->hasPermission('promotions.delete');
    }

    /**
     * Determine if user can toggle promotion status.
     *
     * Only admin/staff with promotion management permission.
     */
    public function toggleStatus(User $user, Promotion $promotion): bool
    {
        return $user->hasPermission('promotions.update');
    }

    /**
     * Determine if user can reset usage count.
     *
     * CRITICAL PERMISSION: Resetting usage count affects revenue integrity.
     * Require explicit permission beyond normal update rights.
     */
    public function resetUsageCount(User $user, Promotion $promotion): bool
    {
        return $user->hasPermission('promotions.reset_usage');
    }
}

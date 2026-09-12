<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Promotion;

class PromotionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('promotions.view');
    }

    public function view(User $user, Promotion $promotion): bool
    {
        return $user->hasPermission('promotions.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('promotions.create');
    }

    public function update(User $user, Promotion $promotion): bool
    {
        return $user->hasPermission('promotions.update');
    }

    public function delete(User $user, Promotion $promotion): bool
    {
        return $user->hasPermission('promotions.delete');
    }

    public function toggleStatus(User $user, Promotion $promotion): bool
    {
        return $user->hasPermission('promotions.update');
    }

    public function resetUsageCount(User $user, Promotion $promotion): bool
    {
        return $user->hasPermission('promotions.reset_usage');
    }
}

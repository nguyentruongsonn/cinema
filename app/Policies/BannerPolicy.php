<?php

namespace App\Policies;

use App\Models\Banner;
use App\Models\User;

class BannerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('banners.view');
    }

    public function view(User $user, Banner $banner): bool
    {
        return $user->hasPermission('banners.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('banners.create');
    }

    public function update(User $user, Banner $banner): bool
    {
        return $user->hasPermission('banners.update');
    }

    public function delete(User $user, Banner $banner): bool
    {
        return $user->hasPermission('banners.delete');
    }
}

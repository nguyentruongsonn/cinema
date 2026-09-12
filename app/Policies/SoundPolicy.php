<?php

namespace App\Policies;

use App\Models\Sound;
use App\Models\User;

class SoundPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Sound $sound): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, Sound $sound): bool
    {
        return $this->canManage($user);
    }

    private function canManage(User $user): bool
    {
        return $user->hasPermission('sounds.manage');
    }
}

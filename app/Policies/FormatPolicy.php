<?php

namespace App\Policies;

use App\Models\Format;
use App\Models\User;

class FormatPolicy
{
    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Format $format): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, Format $format): bool
    {
        return $this->canManage($user);
    }

    private function canManage(User $user): bool
    {
        return $user->hasPermission('formats.manage');
    }
}

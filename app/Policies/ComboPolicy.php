<?php

namespace App\Policies;

use App\Models\Combo;
use App\Models\User;

class ComboPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    public function view(User $user, Combo $combo): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Combo $combo): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Combo $combo): bool
    {
        return $this->viewAny($user);
    }
}

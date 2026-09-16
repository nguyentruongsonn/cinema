<?php

namespace App\Policies;

use App\Models\Combo;
use App\Models\User;

class ComboPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('combos.view');
    }

    public function view(User $user, Combo $combo): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('combos.create');
    }

    public function update(User $user, Combo $combo): bool
    {
        return $user->hasPermission('combos.update');
    }

    public function toggleStatus(User $user, Combo $combo): bool
    {
        return $user->hasAnyPermission(['combos.toggle_status', 'combos.update']);
    }

    public function delete(User $user, Combo $combo): bool
    {
        return $user->hasPermission('combos.delete');
    }
}

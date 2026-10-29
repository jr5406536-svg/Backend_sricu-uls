<?php

namespace App\Policies;

use App\Models\User;

class CatalogPolicy
{
    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user): bool
    {
        return $this->manage($user);
    }

    public function delete(User $user): bool
    {
        return $this->manage($user);
    }

    private function manage(User $user): bool
    {
        return $user->is_active && ($user->isAdmin() || $user->hasRole('gestor'));
    }
}

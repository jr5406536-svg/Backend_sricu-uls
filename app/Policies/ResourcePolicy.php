<?php

namespace App\Policies;

use App\Enums\ResourceStatus;
use App\Models\Resource;
use App\Models\User;

class ResourcePolicy
{
    public function create(User $user): bool
    {
        return $user->is_active && $user->hasAnyRole(['administrador', 'gestor', 'depositante']);
    }

    public function update(User $user, Resource $resource): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->isAdmin() || $user->hasRole('gestor')) {
            return $resource->status !== ResourceStatus::Retirado;
        }

        return $resource->owner_id === $user->id && $resource->editableByOwner();
    }

    public function delete(User $user, Resource $resource): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $resource->owner_id === $user->id && $resource->status === ResourceStatus::Borrador;
    }

    public function submit(User $user, Resource $resource): bool
    {
        return $user->is_active
            && $resource->owner_id === $user->id
            && $user->hasAnyRole(['administrador', 'gestor', 'depositante']);
    }

    public function review(User $user, Resource $resource): bool
    {
        return $user->is_active
            && $resource->status === ResourceStatus::Revision
            && ($user->isAdmin() || $user->hasRole('revisor'));
    }

    public function publish(User $user, Resource $resource): bool
    {
        return $user->is_active
            && $resource->status === ResourceStatus::Aprobado
            && ($user->isAdmin() || $user->hasRole('gestor'));
    }

    public function withdraw(User $user, Resource $resource): bool
    {
        return $user->is_active
            && $resource->status === ResourceStatus::Publicado
            && ($user->isAdmin() || $user->hasRole('gestor'));
    }

    public function upload(User $user, Resource $resource): bool
    {
        return $this->update($user, $resource);
    }
}

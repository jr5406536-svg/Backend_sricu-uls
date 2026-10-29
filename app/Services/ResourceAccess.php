<?php

namespace App\Services;

use App\Enums\AccessLevel;
use App\Enums\ResourceStatus;
use App\Models\Resource;
use App\Models\User;

class ResourceAccess
{
    public function canView(?User $user, Resource $resource): bool
    {
        if ($user?->is_active && ($user->isAdmin() || $user->hasRole('gestor'))) {
            return true;
        }

        if ($user && $resource->owner_id === $user->id) {
            return true;
        }

        if ($user?->is_active && $user->hasRole('revisor') && $resource->status === ResourceStatus::Revision) {
            return true;
        }

        if ($resource->status !== ResourceStatus::Publicado) {
            return false;
        }

        return match ($resource->access_level) {
            AccessLevel::Publico => true,
            AccessLevel::Autenticado => $user?->is_active === true,
            AccessLevel::Restringido => false,
        };
    }

    public function canDownload(?User $user, Resource $resource): bool
    {
        if ($resource->status !== ResourceStatus::Publicado || ! $resource->license?->allows_download) {
            return false;
        }

        if ($user?->is_active && ($user->isAdmin() || $user->hasRole('gestor') || $resource->owner_id === $user->id)) {
            return true;
        }

        return match ($resource->access_level) {
            AccessLevel::Publico => true,
            AccessLevel::Autenticado => $user?->is_active === true,
            AccessLevel::Restringido => false,
        };
    }
}

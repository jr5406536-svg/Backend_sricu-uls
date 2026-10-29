<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    public function log(string $action, Model $model, array $metadata = [], ?User $user = null): AuditLog
    {
        return AuditLog::query()->create([
            'user_id' => $user?->id ?? auth()->id(),
            'action' => $action,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'metadata' => $metadata,
            'ip' => request()->ip(),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;

trait HandlesCatalogDeletion
{
    protected function deleteCatalog(Model $model, AuditLogger $audit, string $action, ?User $user): JsonResponse
    {
        try {
            $model->delete();
        } catch (QueryException) {
            return response()->json([
                'message' => 'No se puede eliminar porque hay recursos asociados.',
            ], 409);
        }

        $audit->log($action, $model, ['name' => $model->name ?? null], $user);

        return response()->json(['message' => 'Registro eliminado.']);
    }
}

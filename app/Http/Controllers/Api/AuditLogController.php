<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'action' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $logs = AuditLog::query()
            ->with('user')
            ->when($data['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->when($data['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($data['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return AuditLogResource::collection($logs)->additional([
            'message' => $logs->total() === 0 ? 'No hay eventos de auditoría en el periodo seleccionado.' : null,
        ]);
    }
}

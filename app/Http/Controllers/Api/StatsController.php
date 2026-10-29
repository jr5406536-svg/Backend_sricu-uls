<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Resource;
use App\Models\ResourceEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = isset($data['from']) ? Carbon::parse($data['from'])->startOfDay() : now()->startOfMonth();
        $to = isset($data['to']) ? Carbon::parse($data['to'])->endOfDay() : now()->endOfDay();

        $created = Resource::query()->whereBetween('created_at', [$from, $to]);
        $events = ResourceEvent::query()->whereBetween('created_at', [$from, $to]);
        $views = (clone $events)->where('type', 'view')->count();
        $downloads = (clone $events)->where('type', 'download')->count();
        $searches = (clone $events)->where('type', 'search')->count();
        $createdCount = (clone $created)->count();

        $message = ($views + $downloads + $searches + $createdCount) === 0
            ? 'No hay eventos en el periodo seleccionado.'
            : null;

        return response()->json([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'resources_created' => $createdCount,
            'published' => Resource::query()
                ->where('status', 'publicado')
                ->whereBetween('published_at', [$from, $to])
                ->count(),
            'by_status' => (clone $created)
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status'),
            'by_type' => (clone $created)
                ->select('type', DB::raw('count(*) as total'))
                ->groupBy('type')
                ->pluck('total', 'type'),
            'views' => $views,
            'downloads' => $downloads,
            'searches' => $searches,
            'message' => $message,
        ]);
    }
}

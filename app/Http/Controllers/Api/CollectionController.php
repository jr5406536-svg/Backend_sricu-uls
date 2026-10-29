<?php

namespace App\Http\Controllers\Api;

use App\Enums\AccessLevel;
use App\Enums\ResourceStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\HandlesCatalogDeletion;
use App\Http\Resources\CollectionResource;
use App\Models\Collection;
use App\Services\AuditLogger;
use App\Support\Slugger;
use Illuminate\Http\Request;

class CollectionController extends Controller
{
    use HandlesCatalogDeletion;

    public function index()
    {
        $collections = Collection::query()
            ->withCount(['resources' => function ($query) {
                $query->where('status', ResourceStatus::Publicado->value)
                    ->where('access_level', AccessLevel::Publico->value);
            }])
            ->orderBy('name')
            ->get();

        return CollectionResource::collection($collections);
    }

    public function store(Request $request, AuditLogger $audit)
    {
        $this->authorize('create', Collection::class);
        $data = $this->validated($request);
        $data['slug'] = Slugger::unique(Collection::class, $data['name'], $data['slug'] ?? null);
        $collection = Collection::query()->create($data);
        $audit->log('coleccion.creada', $collection, [], $request->user());

        return (new CollectionResource($collection))->response()->setStatusCode(201);
    }

    public function show(Collection $collection)
    {
        $collection->loadCount(['resources' => function ($query) {
            $query->where('status', ResourceStatus::Publicado->value)
                ->where('access_level', AccessLevel::Publico->value);
        }]);

        return new CollectionResource($collection);
    }

    public function resources(Request $request, Collection $collection, ResourceController $resources)
    {
        $request->merge(['collection_id' => $collection->id]);

        return $resources->index($request);
    }

    public function update(Request $request, Collection $collection, AuditLogger $audit)
    {
        $this->authorize('update', $collection);
        $data = $this->validated($request, true);
        if (array_key_exists('name', $data) || array_key_exists('slug', $data)) {
            $data['slug'] = Slugger::unique(
                Collection::class,
                $data['name'] ?? $collection->name,
                $data['slug'] ?? null,
                $collection->id,
            );
        }
        $collection->fill($data)->save();
        $audit->log('coleccion.actualizada', $collection, [], $request->user());

        return new CollectionResource($collection);
    }

    public function destroy(Request $request, Collection $collection, AuditLogger $audit)
    {
        $this->authorize('delete', $collection);

        return $this->deleteCatalog($collection, $audit, 'coleccion.eliminada', $request->user());
    }

    private function validated(Request $request, bool $update = false): array
    {
        return $request->validate([
            'name' => [$update ? 'sometimes' : 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);
    }
}

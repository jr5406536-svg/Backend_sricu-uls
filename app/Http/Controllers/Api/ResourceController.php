<?php

namespace App\Http\Controllers\Api;

use App\Enums\AccessLevel;
use App\Enums\ResourceStatus;
use App\Enums\ResourceType;
use App\Enums\ReviewDecision;
use App\Http\Controllers\Controller;
use App\Http\Resources\ResourceItemResource;
use App\Models\Resource;
use App\Models\ResourceEvent;
use App\Services\AuditLogger;
use App\Services\ResourceAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ResourceController extends Controller
{
    public function __construct(private ResourceAccess $access, private AuditLogger $audit) {}

    public function index(Request $request)
    {
        $query = $this->visibleQuery($request)->with($this->relations());
        $this->applyFilters($query, $request);
        $this->applySort($query, $request);

        if ($request->filled('q')) {
            $this->recordEvent(null, $request, 'search', ['q' => $request->string('q')->toString()]);
        }

        $perPage = min(max($request->integer('per_page', 15), 1), 50);
        $page = $query->paginate($perPage)->withQueryString();

        return ResourceItemResource::collection($page)->additional([
            'message' => $page->total() === 0
                ? 'No se encontraron recursos con esos criterios.'
                : null,
        ]);
    }

    public function semantic(Request $request)
    {
        $request->validate([
            'q' => ['required', 'string', 'max:255'],
        ]);

        if (! config('repository.semantic_enabled')) {
            return response()->json([
                'enabled' => false,
                'data' => [],
                'message' => 'La búsqueda semántica no está habilitada. Use la búsqueda por texto.',
            ]);
        }

        return response()->json([
            'enabled' => true,
            'data' => [],
            'message' => 'No hay similitud suficiente para recomendar recursos.',
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Resource::class);
        $data = $this->validated($request);

        $resource = Resource::query()->create([
            ...collect($data)->except(['author_ids', 'keyword_ids', 'access_level'])->all(),
            'language' => $data['language'] ?? 'es',
            'status' => ResourceStatus::Borrador,
            'access_level' => AccessLevel::Restringido,
            'owner_id' => $request->user()->id,
        ]);
        $resource->syncAuthors($data['author_ids']);
        $resource->syncKeywords($data['keyword_ids'] ?? []);
        $this->audit->log('recurso.creado', $resource, ['title' => $resource->title], $request->user());

        return (new ResourceItemResource($resource->load($this->relations())))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Resource $resource)
    {
        $resource->load($this->relations());

        if (! $this->access->canView($request->user(), $resource)) {
            if (
                $resource->status === ResourceStatus::Publicado
                && $resource->access_level === AccessLevel::Autenticado
                && ! $request->user()
            ) {
                return response()->json(['message' => 'Debe iniciar sesión para consultar este recurso.'], 401);
            }

            abort(404);
        }

        if ($resource->status === ResourceStatus::Publicado) {
            $this->recordEvent($resource, $request, 'view');
        }

        return new ResourceItemResource($resource);
    }

    public function update(Request $request, Resource $resource)
    {
        $this->authorize('update', $resource);
        $data = $this->validated($request, true);
        $before = $resource->only([
            'title', 'description', 'type', 'resource_date', 'language', 'license_id',
            'category_id', 'collection_id', 'provenance', 'external_url',
            'technical_description', 'technologies',
        ]);

        $resource->fill(collect($data)->except(['author_ids', 'keyword_ids'])->all());
        $dirty = array_keys($resource->getDirty());
        $resource->save();

        if (array_key_exists('author_ids', $data)) {
            $resource->syncAuthors($data['author_ids']);
            $dirty[] = 'author_ids';
        }
        if (array_key_exists('keyword_ids', $data)) {
            $resource->syncKeywords($data['keyword_ids']);
            $dirty[] = 'keyword_ids';
        }

        if ($dirty !== []) {
            $this->audit->log('recurso.actualizado', $resource, [
                'fields' => $dirty,
                'before' => $before,
            ], $request->user());
        }

        return new ResourceItemResource($resource->load($this->relations()));
    }

    public function destroy(Request $request, Resource $resource)
    {
        $this->authorize('delete', $resource);
        $this->audit->log('recurso.eliminado', $resource, ['title' => $resource->title], $request->user());
        $resource->delete();

        return response()->json(['message' => 'Recurso eliminado.']);
    }

    public function submit(Request $request, Resource $resource)
    {
        $this->authorize('submit', $resource);
        $resource->submit($request->user());

        return new ResourceItemResource($resource->fresh()->load($this->relations()));
    }

    public function review(Request $request, Resource $resource)
    {
        $this->authorize('review', $resource);
        $data = $request->validate([
            'decision' => ['required', Rule::enum(ReviewDecision::class)],
            'observations' => ['nullable', 'string'],
        ]);

        $resource->review(
            $request->user(),
            ReviewDecision::from($data['decision']),
            $data['observations'] ?? null,
        );

        return new ResourceItemResource($resource->fresh()->load($this->relations()));
    }

    public function publish(Request $request, Resource $resource)
    {
        $this->authorize('publish', $resource);
        $data = $request->validate([
            'access_level' => ['required', Rule::enum(AccessLevel::class)],
        ]);

        $resource->publish($request->user(), AccessLevel::from($data['access_level']));

        return new ResourceItemResource($resource->fresh()->load($this->relations()));
    }

    public function withdraw(Request $request, Resource $resource)
    {
        $this->authorize('withdraw', $resource);
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $resource->withdraw($request->user(), $data['reason']);

        return new ResourceItemResource($resource->fresh()->load($this->relations()));
    }

    private function validated(Request $request, bool $update = false): array
    {
        $required = $update ? 'sometimes' : 'required';

        return $request->validate([
            'title' => [$required, 'string', 'max:255'],
            'description' => [$required, 'string'],
            'type' => [$required, Rule::enum(ResourceType::class)],
            'resource_date' => ['nullable', 'date'],
            'language' => ['nullable', 'string', 'max:10'],
            'license_id' => [$required, 'exists:licenses,id'],
            'category_id' => [$required, 'exists:categories,id'],
            'collection_id' => ['nullable', 'exists:collections,id'],
            'author_ids' => [$required, 'array', 'min:1'],
            'author_ids.*' => ['integer', 'exists:authors,id'],
            'keyword_ids' => ['nullable', 'array'],
            'keyword_ids.*' => ['integer', 'exists:keywords,id'],
            'provenance' => ['nullable', 'string', 'max:500'],
            'external_url' => ['nullable', 'url', 'max:500'],
            'technical_description' => ['nullable', 'string', 'required_if:type,proyecto'],
            'technologies' => ['nullable', 'string', 'max:500'],
        ]);
    }

    private function relations(): array
    {
        return ['license', 'category', 'collection', 'authors', 'keywords', 'files', 'reviews.reviewer'];
    }

    private function visibleQuery(Request $request)
    {
        $query = Resource::query();
        $user = $request->user();

        if ($user?->is_active && ($user->isAdmin() || $user->hasRole('gestor'))) {
            return $query;
        }

        return $query->where(function ($query) use ($user) {
            $query->where(function ($query) {
                $query->where('status', ResourceStatus::Publicado->value)
                    ->where('access_level', AccessLevel::Publico->value);
            });

            if ($user?->is_active) {
                $query->orWhere(function ($query) {
                    $query->where('status', ResourceStatus::Publicado->value)
                        ->where('access_level', AccessLevel::Autenticado->value);
                });
            }

            if ($user) {
                $query->orWhere('owner_id', $user->id);
            }

            if ($user?->is_active && $user->hasRole('revisor')) {
                $query->orWhere('status', ResourceStatus::Revision->value);
            }
        });
    }

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('q')) {
            $term = '%'.addcslashes($request->string('q')->toString(), '%_\\').'%';
            $query->where(function ($query) use ($term) {
                $query->where('title', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }
        if ($request->filled('collection_id')) {
            $query->where('collection_id', $request->integer('collection_id'));
        }
        if ($request->filled('license_id')) {
            $query->where('license_id', $request->integer('license_id'));
        }
        if ($request->filled('author_id')) {
            $query->whereHas('authors', fn ($query) => $query->where('authors.id', $request->integer('author_id')));
        }
        if ($request->filled('keyword_id')) {
            $query->whereHas('keywords', fn ($query) => $query->where('keywords.id', $request->integer('keyword_id')));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('resource_date', '>=', $request->date('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('resource_date', '<=', $request->date('date_to'));
        }

        $user = $request->user();
        if ($request->filled('status') && $user?->is_active && $user->hasAnyRole(['administrador', 'gestor', 'revisor', 'depositante'])) {
            $query->where('status', $request->string('status')->toString());
        }
    }

    private function applySort($query, Request $request): void
    {
        $sort = $request->string('sort', 'published_at')->toString();
        $allowed = ['title', 'resource_date', 'created_at', 'published_at'];
        if (! in_array($sort, $allowed, true)) {
            $sort = 'published_at';
        }
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sort, $direction)->orderBy('id', 'desc');
    }

    private function recordEvent(?Resource $resource, Request $request, string $type, array $metadata = []): void
    {
        ResourceEvent::query()->create([
            'resource_id' => $resource?->id,
            'user_id' => $request->user()?->id,
            'type' => $type,
            'metadata' => $metadata ?: null,
            'created_at' => now(),
        ]);
    }
}

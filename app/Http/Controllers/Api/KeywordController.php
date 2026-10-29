<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\HandlesCatalogDeletion;
use App\Http\Resources\KeywordResource;
use App\Models\Keyword;
use App\Services\AuditLogger;
use App\Support\Slugger;
use Illuminate\Http\Request;

class KeywordController extends Controller
{
    use HandlesCatalogDeletion;

    public function index()
    {
        return KeywordResource::collection(Keyword::query()->orderBy('name')->get());
    }

    public function store(Request $request, AuditLogger $audit)
    {
        $this->authorize('create', Keyword::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
        ]);
        $data['slug'] = Slugger::unique(Keyword::class, $data['name'], $data['slug'] ?? null);
        $keyword = Keyword::query()->create($data);
        $audit->log('palabra_clave.creada', $keyword, [], $request->user());

        return (new KeywordResource($keyword))->response()->setStatusCode(201);
    }

    public function show(Keyword $keyword)
    {
        return new KeywordResource($keyword);
    }

    public function update(Request $request, Keyword $keyword, AuditLogger $audit)
    {
        $this->authorize('update', $keyword);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
        ]);
        if (array_key_exists('name', $data) || array_key_exists('slug', $data)) {
            $data['slug'] = Slugger::unique(
                Keyword::class,
                $data['name'] ?? $keyword->name,
                $data['slug'] ?? null,
                $keyword->id,
            );
        }
        $keyword->fill($data)->save();
        $audit->log('palabra_clave.actualizada', $keyword, [], $request->user());

        return new KeywordResource($keyword);
    }

    public function destroy(Request $request, Keyword $keyword, AuditLogger $audit)
    {
        $this->authorize('delete', $keyword);

        return $this->deleteCatalog($keyword, $audit, 'palabra_clave.eliminada', $request->user());
    }
}

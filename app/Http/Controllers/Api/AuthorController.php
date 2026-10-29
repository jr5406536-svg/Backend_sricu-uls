<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\HandlesCatalogDeletion;
use App\Http\Resources\AuthorResource;
use App\Models\Author;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class AuthorController extends Controller
{
    use HandlesCatalogDeletion;

    public function index()
    {
        return AuthorResource::collection(Author::query()->orderBy('name')->get());
    }

    public function store(Request $request, AuditLogger $audit)
    {
        $this->authorize('create', Author::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'affiliation' => ['nullable', 'string', 'max:255'],
            'orcid' => ['nullable', 'string', 'max:19'],
            'user_id' => ['nullable', 'exists:users,id'],
        ]);

        $author = Author::query()->create($data);
        $audit->log('autor.creado', $author, [], $request->user());

        return (new AuthorResource($author))->response()->setStatusCode(201);
    }

    public function show(Author $author)
    {
        return new AuthorResource($author);
    }

    public function update(Request $request, Author $author, AuditLogger $audit)
    {
        $this->authorize('update', $author);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'affiliation' => ['nullable', 'string', 'max:255'],
            'orcid' => ['nullable', 'string', 'max:19'],
            'user_id' => ['nullable', 'exists:users,id'],
        ]);

        $author->fill($data);
        $dirty = array_keys($author->getDirty());
        $author->save();
        $audit->log('autor.actualizado', $author, ['fields' => $dirty], $request->user());

        return new AuthorResource($author);
    }

    public function destroy(Request $request, Author $author, AuditLogger $audit)
    {
        $this->authorize('delete', $author);

        return $this->deleteCatalog($author, $audit, 'autor.eliminado', $request->user());
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\HandlesCatalogDeletion;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\AuditLogger;
use App\Support\Slugger;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    use HandlesCatalogDeletion;

    public function index()
    {
        return CategoryResource::collection(Category::query()->orderBy('name')->get());
    }

    public function store(Request $request, AuditLogger $audit)
    {
        $this->authorize('create', Category::class);
        $data = $this->validated($request);
        $data['slug'] = Slugger::unique(Category::class, $data['name'], $data['slug'] ?? null);
        $category = Category::query()->create($data);
        $audit->log('categoria.creada', $category, [], $request->user());

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    public function show(Category $category)
    {
        return new CategoryResource($category);
    }

    public function update(Request $request, Category $category, AuditLogger $audit)
    {
        $this->authorize('update', $category);
        $data = $this->validated($request, true);
        if (array_key_exists('name', $data) || array_key_exists('slug', $data)) {
            $data['slug'] = Slugger::unique(
                Category::class,
                $data['name'] ?? $category->name,
                $data['slug'] ?? null,
                $category->id,
            );
        }
        $category->fill($data)->save();
        $audit->log('categoria.actualizada', $category, [], $request->user());

        return new CategoryResource($category);
    }

    public function destroy(Request $request, Category $category, AuditLogger $audit)
    {
        $this->authorize('delete', $category);

        return $this->deleteCatalog($category, $audit, 'categoria.eliminada', $request->user());
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

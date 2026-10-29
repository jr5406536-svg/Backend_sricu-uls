<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\HandlesCatalogDeletion;
use App\Http\Resources\LicenseResource;
use App\Models\License;
use App\Services\AuditLogger;
use App\Support\Slugger;
use Illuminate\Http\Request;

class LicenseController extends Controller
{
    use HandlesCatalogDeletion;

    public function index()
    {
        return LicenseResource::collection(License::query()->orderBy('name')->get());
    }

    public function store(Request $request, AuditLogger $audit)
    {
        $this->authorize('create', License::class);
        $data = $this->validated($request);
        $data['slug'] = Slugger::unique(License::class, $data['name'], $data['slug'] ?? null);
        $license = License::query()->create($data);
        $audit->log('licencia.creada', $license, [], $request->user());

        return (new LicenseResource($license))->response()->setStatusCode(201);
    }

    public function show(License $license)
    {
        return new LicenseResource($license);
    }

    public function update(Request $request, License $license, AuditLogger $audit)
    {
        $this->authorize('update', $license);
        $data = $this->validated($request, true);
        if (array_key_exists('name', $data) || array_key_exists('slug', $data)) {
            $data['slug'] = Slugger::unique(
                License::class,
                $data['name'] ?? $license->name,
                $data['slug'] ?? null,
                $license->id,
            );
        }
        $license->fill($data)->save();
        $audit->log('licencia.actualizada', $license, [], $request->user());

        return new LicenseResource($license);
    }

    public function destroy(Request $request, License $license, AuditLogger $audit)
    {
        $this->authorize('delete', $license);

        return $this->deleteCatalog($license, $audit, 'licencia.eliminada', $request->user());
    }

    private function validated(Request $request, bool $update = false): array
    {
        return $request->validate([
            'name' => [$update ? 'sometimes' : 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:500'],
            'allows_download' => [$update ? 'sometimes' : 'required', 'boolean'],
            'description' => ['nullable', 'string'],
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ResourceFileResource;
use App\Models\Resource;
use App\Models\ResourceEvent;
use App\Models\ResourceFile;
use App\Services\AuditLogger;
use App\Services\ResourceAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResourceFileController extends Controller
{
    public function __construct(private ResourceAccess $access, private AuditLogger $audit) {}

    public function store(Request $request, Resource $resource)
    {
        $this->authorize('upload', $resource);

        $request->validate([
            'file' => ['required', 'file', 'max:'.config('repository.max_upload_kb')],
        ]);

        $uploaded = $request->file('file');
        $extension = strtolower($uploaded->getClientOriginalExtension());
        $allowed = config('repository.allowed_extensions');

        if (! in_array($extension, $allowed, true)) {
            throw ValidationException::withMessages([
                'file' => 'El archivo debe tener una de estas extensiones: '.implode(', ', $allowed).'.',
            ]);
        }

        $name = Str::uuid()->toString().'.'.$extension;
        $path = $uploaded->storeAs('repository/'.$resource->id, $name, 'local');

        $file = $resource->files()->create([
            'original_name' => $uploaded->getClientOriginalName(),
            'stored_path' => $path,
            'mime' => $uploaded->getClientMimeType(),
            'extension' => $extension,
            'size' => $uploaded->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);
        $file->setRelation('resource', $resource->loadMissing('license'));

        $this->audit->log('recurso.archivo_cargado', $resource, [
            'file_id' => $file->id,
            'original_name' => $file->original_name,
            'size' => $file->size,
        ], $request->user());

        return (new ResourceFileResource($file))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, Resource $resource, ResourceFile $file)
    {
        $this->ensureFileBelongsToResource($resource, $file);
        $this->authorize('upload', $resource);
        $this->audit->log('recurso.archivo_eliminado', $resource, [
            'file_id' => $file->id,
            'original_name' => $file->original_name,
        ], $request->user());
        $file->delete();

        return response()->json(['message' => 'Archivo eliminado.']);
    }

    public function download(Request $request, Resource $resource, ResourceFile $file)
    {
        $this->ensureFileBelongsToResource($resource, $file);
        $resource->loadMissing('license');

        if (! $this->access->canDownload($request->user(), $resource)) {
            return response()->json(['message' => 'No tiene permiso para descargar este archivo.'], 403);
        }

        if (! Storage::disk('local')->exists($file->stored_path)) {
            abort(404);
        }

        ResourceEvent::query()->create([
            'resource_id' => $resource->id,
            'user_id' => $request->user()?->id,
            'type' => 'download',
            'created_at' => now(),
        ]);

        return Storage::disk('local')->download($file->stored_path, $file->original_name);
    }

    private function ensureFileBelongsToResource(Resource $resource, ResourceFile $file): void
    {
        if ($file->resource_id !== $resource->id) {
            abort(404);
        }
    }
}

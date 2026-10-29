<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ResourceFile extends Model
{
    protected $fillable = [
        'resource_id',
        'original_name',
        'stored_path',
        'mime',
        'extension',
        'size',
        'uploaded_by',
    ];

    protected static function booted(): void
    {
        static::deleting(function (ResourceFile $file) {
            Storage::disk('local')->delete($file->stored_path);
        });
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

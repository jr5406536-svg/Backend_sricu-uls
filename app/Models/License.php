<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class License extends Model
{
    protected $fillable = ['name', 'slug', 'url', 'allows_download', 'description'];

    protected function casts(): array
    {
        return [
            'allows_download' => 'boolean',
        ];
    }

    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class);
    }
}

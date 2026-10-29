<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Keyword extends Model
{
    protected $fillable = ['name', 'slug'];

    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(Resource::class, 'resource_keyword');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Collection extends Model
{
    protected $fillable = ['name', 'slug', 'description'];

    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class);
    }
}

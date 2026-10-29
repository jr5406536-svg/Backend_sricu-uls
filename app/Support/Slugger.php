<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Slugger
{
    public static function unique(string $modelClass, string $name, ?string $preferred = null, ?int $ignoreId = null): string
    {
        /** @var class-string<Model> $modelClass */
        $base = Str::slug($preferred ?: $name) ?: 'item';
        $slug = $base;
        $suffix = 2;

        while ($modelClass::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}

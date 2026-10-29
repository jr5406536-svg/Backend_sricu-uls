<?php

namespace App\Models;

use App\Enums\ReviewDecision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = ['resource_id', 'reviewer_id', 'decision', 'observations'];

    protected function casts(): array
    {
        return [
            'decision' => ReviewDecision::class,
        ];
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}

<?php

namespace App\Models;

use App\Enums\AccessLevel;
use App\Enums\ResourceStatus;
use App\Enums\ResourceType;
use App\Enums\ReviewDecision;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Resource extends Model
{
    protected $fillable = [
        'title',
        'description',
        'type',
        'resource_date',
        'language',
        'license_id',
        'category_id',
        'collection_id',
        'status',
        'access_level',
        'provenance',
        'external_url',
        'technical_description',
        'technologies',
        'owner_id',
        'published_at',
        'withdrawn_at',
        'withdrawal_reason',
    ];

    protected function casts(): array
    {
        return [
            'type' => ResourceType::class,
            'status' => ResourceStatus::class,
            'access_level' => AccessLevel::class,
            'resource_date' => 'date',
            'published_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Resource $resource) {
            $resource->files->each->delete();
        });
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class, 'resource_author')
            ->withPivot('position')
            ->orderByPivot('position');
    }

    public function keywords(): BelongsToMany
    {
        return $this->belongsToMany(Keyword::class, 'resource_keyword');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ResourceFile::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class)->latest();
    }

    public function syncAuthors(array $authorIds): void
    {
        $sync = [];
        foreach (array_values($authorIds) as $index => $authorId) {
            $sync[$authorId] = ['position' => $index + 1];
        }
        $this->authors()->sync($sync);
    }

    public function syncKeywords(array $keywordIds): void
    {
        $this->keywords()->sync($keywordIds);
    }

    public function editableByOwner(): bool
    {
        return in_array($this->status, [ResourceStatus::Borrador, ResourceStatus::Observado], true);
    }

    public function submit(User $user): void
    {
        if (! $this->editableByOwner()) {
            throw ValidationException::withMessages([
                'status' => 'Solo un borrador u un recurso observado puede enviarse a revisión.',
            ]);
        }

        $this->status = ResourceStatus::Revision;
        $this->save();
        app(AuditLogger::class)->log('recurso.enviado_a_revision', $this, [], $user);
    }

    public function review(User $user, ReviewDecision $decision, ?string $observations): Review
    {
        if ($this->status !== ResourceStatus::Revision) {
            throw ValidationException::withMessages([
                'status' => 'El recurso no está en revisión.',
            ]);
        }

        if ($decision === ReviewDecision::Devuelto && blank($observations)) {
            throw ValidationException::withMessages([
                'observations' => 'Las observaciones son obligatorias al devolver un recurso.',
            ]);
        }

        $review = $this->reviews()->create([
            'reviewer_id' => $user->id,
            'decision' => $decision,
            'observations' => $observations,
        ]);

        $this->status = $decision === ReviewDecision::Aprobado
            ? ResourceStatus::Aprobado
            : ResourceStatus::Observado;
        $this->save();

        app(AuditLogger::class)->log('recurso.revisado', $this, [
            'decision' => $decision->value,
            'review_id' => $review->id,
            'observations' => $observations,
        ], $user);

        return $review;
    }

    public function publish(User $user, AccessLevel $accessLevel): void
    {
        if ($this->status !== ResourceStatus::Aprobado) {
            throw ValidationException::withMessages([
                'status' => 'Solo se puede publicar un recurso aprobado.',
            ]);
        }

        $this->status = ResourceStatus::Publicado;
        $this->access_level = $accessLevel;
        $this->published_at = now();
        $this->withdrawn_at = null;
        $this->withdrawal_reason = null;
        $this->save();

        app(AuditLogger::class)->log('recurso.publicado', $this, [
            'access_level' => $accessLevel->value,
            'license_id' => $this->license_id,
        ], $user);
    }

    public function withdraw(User $user, string $reason): void
    {
        if ($this->status !== ResourceStatus::Publicado) {
            throw ValidationException::withMessages([
                'status' => 'Solo se puede retirar un recurso publicado.',
            ]);
        }

        $this->status = ResourceStatus::Retirado;
        $this->withdrawn_at = now();
        $this->withdrawal_reason = $reason;
        $this->save();

        app(AuditLogger::class)->log('recurso.retirado', $this, [
            'reason' => $reason,
        ], $user);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\HasActivityLogs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'action', 'subject_type', 'subject_id', 'properties', 'created_at'];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'subject_type', 'subject_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForSubject(Builder $query, Model $subject): Builder
    {
        return $query
            ->where('subject_type', $subject::class)
            ->where('subject_id', $subject->getKey());
    }

    public static function record(string $action, ?Model $subject = null, array $properties = []): void
    {
        static::log($action, $subject, $properties);
    }

    public static function log(
        string $action,
        ?Model $subject = null,
        array $properties = [],
        ?int $userId = null,
        ?\DateTimeInterface $occurredAt = null,
    ): self {
        return \App\Support\ActivityLogger::log($action, $subject, $properties, $userId, $occurredAt);
    }
}

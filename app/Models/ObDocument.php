<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ObDocument extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_FINAL = 'final';

    protected $fillable = [
        'legislative_session_id',
        'title',
        'status',
        'next_session_agenda_no',
        'created_by',
    ];

    public function legislativeSession(): BelongsTo
    {
        return $this->belongsTo(LegislativeSession::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(ObBlock::class)->orderBy('sort_order');
    }

    public function statusLabel(): string
    {
        return config('order_of_business.document_statuses.'.$this->status, $this->status);
    }

    public function isFinal(): bool
    {
        return $this->status === self::STATUS_FINAL;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeFinal(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FINAL);
    }
}

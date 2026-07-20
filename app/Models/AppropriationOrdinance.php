<?php

namespace App\Models;

use App\Models\Concerns\NavigatesById;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AppropriationOrdinance extends Model
{
    use NavigatesById;
    use SoftDeletes;

    protected $fillable = [
        'date_received',
        'subject',
        'ordinance_no',
        'series_year',
        'date_passed',
        'date_approved',
        'pdf_url',
        'pdf_path',
        'agenda_item_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date_received' => 'date',
            'date_passed' => 'date',
            'date_approved' => 'date',
            'ordinance_no' => 'integer',
            'series_year' => 'integer',
        ];
    }

    public function agendaItem(): BelongsTo
    {
        return $this->belongsTo(AgendaItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function displayNumber(): string
    {
        return 'Appro. Ord. No. '.str_pad((string) $this->ordinance_no, 2, '0', STR_PAD_LEFT);
    }

    public function displaySeries(): string
    {
        return 'Series of '.($this->series_year ?: now()->year);
    }

    public function isPassed(): bool
    {
        return $this->date_passed !== null;
    }

    public function isApproved(): bool
    {
        return $this->date_approved !== null;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('series_year')->orderByDesc('ordinance_no');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        if (ctype_digit($term)) {
            return $query->where(function (Builder $query) use ($term): void {
                $query->where('ordinance_no', (int) $term)
                    ->orWhere('series_year', (int) $term);
            });
        }

        return $query->where('subject', 'like', "%{$term}%");
    }

    protected static function booted(): void
    {
        static::deleting(function (AppropriationOrdinance $appropriationOrdinance): void {
            app(\App\Services\AgendaPublishedOutputService::class)
                ->clearFromDeletedAppropriationOrdinance($appropriationOrdinance);
        });
    }

    public function hasLocalPdf(): bool
    {
        return app(\App\Services\AppropriationOrdinancePdfService::class)->existsFor($this);
    }

    public function pdfPublicUrl(): ?string
    {
        return app(\App\Services\AppropriationOrdinancePdfService::class)->publicUrl($this);
    }

    public function pdfViewerMode(): ?string
    {
        return app(\App\Services\AppropriationOrdinancePdfService::class)->viewerMode($this);
    }

    public function needsPdfMirror(): bool
    {
        return app(\App\Services\AppropriationOrdinancePdfService::class)->needsMirror($this);
    }
}

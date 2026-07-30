<?php

namespace App\Models;

use App\Models\Concerns\NavigatesById;
use App\Services\AgendaPublishedOutputService;
use App\Services\AppropriationOrdinancePdfService;
use App\Support\Permalink;
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
        'current_version_no',
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
            'current_version_no' => 'integer',
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

    public function versions(): HasMany
    {
        return $this->hasMany(AppropriationOrdinanceVersion::class)->orderByDesc('version_no');
    }

    public function currentVersion(): ?AppropriationOrdinanceVersion
    {
        return $this->versions()->where('version_no', $this->current_version_no)->first()
            ?? $this->versions()->first();
    }

    public function displayNumber(): string
    {
        return 'Appro. Ord. No. '.str_pad((string) $this->ordinance_no, 2, '0', STR_PAD_LEFT);
    }

    public function permalinkYear(): int
    {
        return (int) ($this->series_year ?: $this->created_at?->year ?: now()->year);
    }

    public function getRouteKey(): string
    {
        return Permalink::yearAndNumber($this->permalinkYear(), (int) $this->ordinance_no);
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return $this->resolvePermalinkBinding($this->newQuery(), (string) $value);
    }

    public function resolveSoftDeletableRouteBinding($value, $field = null)
    {
        return $this->resolvePermalinkBinding(static::withTrashed(), (string) $value);
    }

    /**
     * @param  Builder<self>  $query
     */
    protected function resolvePermalinkBinding(Builder $query, string $value): ?self
    {
        if (Permalink::isLegacyNumericId($value)) {
            return $query->whereKey((int) $value)->first();
        }

        $parsed = Permalink::parseYearAndNumber($value);

        if ($parsed === null) {
            return null;
        }

        return $query
            ->where('series_year', $parsed['year'])
            ->where('ordinance_no', $parsed['number'])
            ->orderByDesc($this->getKeyName())
            ->first();
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
            app(AgendaPublishedOutputService::class)
                ->clearFromDeletedAppropriationOrdinance($appropriationOrdinance);
        });
    }

    public function hasLocalPdf(): bool
    {
        return app(AppropriationOrdinancePdfService::class)->existsFor($this);
    }

    public function pdfPublicUrl(): ?string
    {
        return app(AppropriationOrdinancePdfService::class)->publicUrl($this);
    }

    public function pdfViewerMode(): ?string
    {
        return app(AppropriationOrdinancePdfService::class)->viewerMode($this);
    }

    public function needsPdfMirror(): bool
    {
        return app(AppropriationOrdinancePdfService::class)->needsMirror($this);
    }
}

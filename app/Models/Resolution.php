<?php

namespace App\Models;

use App\Models\Concerns\HasActivityLogs;
use App\Services\AgendaPublishedOutputService;
use App\Support\Permalink;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Resolution extends Model
{
    use HasActivityLogs;
    use SoftDeletes;

    protected $fillable = [
        'legacy_sp_id',
        'incoming_document_id',
        'legacy_file_id',
        'legacy_sp_res_no',
        'sp_sequence',
        'mun_resolution_no',
        'mun_title',
        'mun_series',
        'date_received',
        'action_taken',
        'agenda',
        'concerned_agency',
        'remarks',
        'sp_pdf_url',
        'mun_pdf_url',
        'resolution_no',
        'resolution_title',
        'current_version_no',
        'document_type',
        'pdf_path',
        'series',
        'department_id',
        'date_approved',
        'sponsored_by',
        'category_id',
        'category2_id',
        'category3_id',
        'category4_id',
        'keyword',
        'committee',
        'app_ord_no',
        'amount',
        'municipality_id',
        'province',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date_approved' => 'date',
            'province' => 'boolean',
            'series' => 'integer',
            'amount' => 'integer',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function category2(): BelongsTo
    {
        return $this->belongsTo(Category2::class);
    }

    public function category3(): BelongsTo
    {
        return $this->belongsTo(Category3::class);
    }

    public function category4(): BelongsTo
    {
        return $this->belongsTo(Category4::class);
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ResolutionVersion::class)->orderByDesc('version_no');
    }

    public function incomingDocument(): BelongsTo
    {
        return $this->belongsTo(IncomingDocument::class);
    }

    public function publishedFromAgenda(): HasOne
    {
        return $this->hasOne(AgendaItem::class, 'resolution_id')->withTrashed();
    }

    public function permalinkYear(): int
    {
        return (int) ($this->series ?: $this->created_at?->year ?: now()->year);
    }

    public function getRouteKey(): string
    {
        $number = trim((string) ($this->resolution_no ?? ''));

        if ($number === '') {
            return Permalink::yearAndId($this->permalinkYear(), $this->getKey());
        }

        if ($this->hasDuplicateResolutionNo()) {
            $legacySpId = $this->legacy_sp_id;

            if ($legacySpId !== null && $legacySpId !== '') {
                return Permalink::resolutionDuplicateKey($number, $legacySpId);
            }

            return Permalink::resolutionDuplicateKey($number, $this->getKey());
        }

        return $number;
    }

    public function hasDuplicateResolutionNo(): bool
    {
        $number = trim((string) ($this->resolution_no ?? ''));

        if ($number === '') {
            return false;
        }

        static $duplicateCache = [];

        if (! array_key_exists($number, $duplicateCache)) {
            $duplicateCache[$number] = static::withTrashed()
                ->where('resolution_no', $number)
                ->limit(2)
                ->pluck($this->getKeyName())
                ->count() > 1;
        }

        return $duplicateCache[$number];
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
        $duplicate = Permalink::parseResolutionDuplicateKey($value);

        if ($duplicate !== null) {
            $byLegacy = (clone $query)
                ->where('resolution_no', $duplicate['resolution_no'])
                ->where('legacy_sp_id', $duplicate['legacy_sp_id'])
                ->orderByDesc($this->getKeyName())
                ->first();

            if ($byLegacy !== null) {
                return $byLegacy;
            }

            $byIdSuffix = (clone $query)
                ->where('resolution_no', $duplicate['resolution_no'])
                ->whereKey($duplicate['legacy_sp_id'])
                ->first();

            if ($byIdSuffix !== null) {
                return $byIdSuffix;
            }
        }

        $byNumber = (clone $query)
            ->where('resolution_no', $value)
            ->orderByDesc($this->getKeyName())
            ->first();

        if ($byNumber !== null) {
            return $byNumber;
        }

        if (Permalink::isLegacyNumericId($value)) {
            return $query->whereKey((int) $value)->first();
        }

        $parsed = Permalink::parseYearAndId($value);

        if ($parsed === null) {
            return null;
        }

        return $query->whereKey($parsed['id'])->first();
    }

    public function previousInList(): ?self
    {
        return static::query()
            ->where('id', '>', $this->id)
            ->orderBy('id')
            ->first();
    }

    public function nextInList(): ?self
    {
        return static::query()
            ->where('id', '<', $this->id)
            ->orderByDesc('id')
            ->first();
    }

    protected static function booted(): void
    {
        static::forceDeleting(function (Resolution $resolution): void {
            app(AgendaPublishedOutputService::class)->clearFromDeletedResolution($resolution);
        });
    }
}

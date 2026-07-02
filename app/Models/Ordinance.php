<?php

namespace App\Models;

use App\Enums\OrdinancePublicationStatus;
use App\Support\OrdinanceNumberParser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Ordinance extends Model
{
    protected $fillable = [
        'ordinance_no',
        'series_year',
        'subject',
        'publication_status',
        'pdf_url',
        'date_enacted',
        'date_approved',
        'date_posted',
        'date_published_newspaper',
        'effectivity_date',
        'mov_bulletin',
        'mov_bulletin_url',
        'mov_certification',
        'mov_certification_url',
        'mov_newspaper',
        'mov_newspaper_url',
        'implementing_bodies',
        'classification',
        'mandate_ppa',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'ordinance_no' => 'integer',
            'series_year' => 'integer',
            'publication_status' => OrdinancePublicationStatus::class,
            'date_enacted' => 'date',
            'date_approved' => 'date',
            'date_posted' => 'date',
            'date_published_newspaper' => 'date',
            'effectivity_date' => 'date',
        ];
    }

    /**
     * @return BelongsToMany<BoardMember, $this>
     */
    public function authoredSponsoredMembers(): BelongsToMany
    {
        return $this->belongsToMany(BoardMember::class, 'ordinance_board_member')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    public function authoredSponsoredDisplay(): ?string
    {
        $members = $this->relationLoaded('authoredSponsoredMembers')
            ? $this->authoredSponsoredMembers
            : $this->authoredSponsoredMembers()->get();

        if ($members->isEmpty()) {
            return null;
        }

        return $members->map(fn (BoardMember $member) => $member->displayName())->implode(' & ');
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
    public function scopeForSeriesYear(Builder $query, ?int $year): Builder
    {
        if ($year === null || $year <= 0) {
            return $query;
        }

        return $query->where('series_year', $year);
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

        $ordinanceNo = OrdinanceNumberParser::parse($term);

        if ($ordinanceNo !== null) {
            return $query->where('ordinance_no', $ordinanceNo);
        }

        if (ctype_digit($term)) {
            return $query->where(function (Builder $query) use ($term): void {
                $query->where('ordinance_no', (int) $term)
                    ->orWhere('series_year', (int) $term);
            });
        }

        return $query->where(function (Builder $query) use ($term): void {
            $query->where('subject', 'like', "%{$term}%")
                ->orWhere('implementing_bodies', 'like', "%{$term}%")
                ->orWhere('remarks', 'like', "%{$term}%");
        });
    }

    public function displayNumber(): string
    {
        return 'Ord. No. '.str_pad((string) $this->ordinance_no, 2, '0', STR_PAD_LEFT);
    }

    public function displaySeries(): string
    {
        return 'Series of '.($this->series_year ?: now()->year);
    }

    public function shortSubject(int $length = 120): string
    {
        $subject = trim((string) ($this->subject ?? ''));

        if (mb_strlen($subject) <= $length) {
            return $subject;
        }

        return rtrim(mb_substr($subject, 0, $length - 1)).'…';
    }

    public function publicationStatusLabel(): ?string
    {
        return $this->publication_status?->label();
    }

    public function publicationStatusBadgeClass(): ?string
    {
        return $this->publication_status?->badgeClass();
    }

    public function publicationStatusMarkerDotClass(): ?string
    {
        return $this->publication_status?->markerDotClass();
    }

    public function publicationStatusPanelClass(): ?string
    {
        return $this->publication_status?->panelClass();
    }

    public function publicationStatusIconUrl(): ?string
    {
        return $this->publication_status
            ? asset($this->publication_status->iconPath())
            : null;
    }
}

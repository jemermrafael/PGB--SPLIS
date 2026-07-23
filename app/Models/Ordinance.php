<?php

namespace App\Models;

use App\Enums\OrdinanceBoardMemberRole;
use App\Enums\OrdinancePublicationStatus;
use App\Models\Concerns\NavigatesById;
use App\Support\OrdinanceNumberParser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Ordinance extends Model
{
    use NavigatesById;
    use SoftDeletes;

    protected $fillable = [
        'ordinance_no',
        'series_year',
        'title',
        'current_version_no',
        'subject',
        'publication_status',
        'pdf_url',
        'pdf_path',
        'date_enacted',
        'date_approved',
        'date_posted',
        'date_published_newspaper',
        'effectivity_date',
        'mov_bulletin',
        'mov_bulletin_url',
        'mov_bulletin_pdf_path',
        'mov_certification',
        'mov_certification_url',
        'mov_certification_pdf_path',
        'mov_newspaper',
        'mov_newspaper_url',
        'mov_newspaper_pdf_path',
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
            'current_version_no' => 'integer',
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
    public function boardMembers(): BelongsToMany
    {
        return $this->belongsToMany(BoardMember::class, 'ordinance_board_member')
            ->withPivot(['role', 'sort_order'])
            ->orderByPivot('sort_order');
    }

    public function publishedFromAgenda(): HasOne
    {
        return $this->hasOne(AgendaItem::class, 'ordinance_id')->withTrashed();
    }

    /**
     * @return HasMany<OrdinanceVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(OrdinanceVersion::class)->orderByDesc('version_no');
    }

    public function currentVersion(): ?OrdinanceVersion
    {
        if ($this->relationLoaded('versions')) {
            return $this->versions->first();
        }

        return $this->versions()->orderByDesc('version_no')->first();
    }

    /**
     * @return BelongsToMany<BoardMember, $this>
     */
    public function authoredSponsoredMembers(): BelongsToMany
    {
        return $this->boardMembers()
            ->wherePivot('role', OrdinanceBoardMemberRole::AuthoredSponsored->value);
    }

    /**
     * @return Collection<int, BoardMember>
     */
    public function membersForRole(OrdinanceBoardMemberRole $role): Collection
    {
        $members = $this->relationLoaded('boardMembers')
            ? $this->boardMembers
            : $this->boardMembers()->get();

        return $members
            ->filter(fn (BoardMember $member) => $member->pivot->role === $role->value)
            ->values();
    }

    public function authorsDisplay(): ?string
    {
        return $this->roleMembersDisplay(OrdinanceBoardMemberRole::Author);
    }

    public function sponsorsDisplay(): ?string
    {
        return $this->roleMembersDisplay(OrdinanceBoardMemberRole::Sponsor);
    }

    public function authoredSponsoredDisplay(): ?string
    {
        return $this->roleMembersDisplay(OrdinanceBoardMemberRole::AuthoredSponsored);
    }

    public function boardMembersAttributionDisplay(): ?string
    {
        $parts = array_values(array_filter([
            $this->labeledRoleDisplay(OrdinanceBoardMemberRole::AuthoredSponsored),
            $this->labeledRoleDisplay(OrdinanceBoardMemberRole::Author),
            $this->labeledRoleDisplay(OrdinanceBoardMemberRole::Sponsor),
        ]));

        return $parts === [] ? null : implode(' | ', $parts);
    }

    protected function roleMembersDisplay(OrdinanceBoardMemberRole $role): ?string
    {
        $names = $this->membersForRole($role)->map(fn (BoardMember $member) => $member->displayName());

        if ($names->isEmpty()) {
            return null;
        }

        return $names->implode(', ');
    }

    protected function labeledRoleDisplay(OrdinanceBoardMemberRole $role): ?string
    {
        $names = $this->roleMembersDisplay($role);

        return $names ? $role->label().': '.$names : null;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForBoardMember(Builder $query, int $boardMemberId): Builder
    {
        return $query->whereHas('boardMembers', fn (Builder $memberQuery) => $memberQuery->where('board_members.id', $boardMemberId));
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
            $query->where('title', 'like', "%{$term}%")
                ->orWhere('subject', 'like', "%{$term}%")
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

    /**
     * Page heading: "Ord. No. 25 - Short title" when title is set.
     */
    public function displayHeading(): string
    {
        $title = trim((string) ($this->title ?? ''));

        if ($title === '') {
            return $this->displayNumber();
        }

        return $this->displayNumber().' - '.$title;
    }

    public function listTitle(): string
    {
        $title = trim((string) ($this->title ?? ''));

        if ($title !== '') {
            return $title;
        }

        return trim((string) ($this->subject ?? ''));
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

    public function hasLocalPdf(): bool
    {
        return $this->hasLocalPdfType(\App\Support\OrdinancePdfType::MAIN);
    }

    public function hasLocalPdfType(string $type): bool
    {
        return app(\App\Services\OrdinancePdfService::class)->existsFor($this, $type);
    }

    public function pdfPublicUrl(): ?string
    {
        return app(\App\Services\OrdinancePdfService::class)->publicUrl($this);
    }

    public function pdfViewerMode(): ?string
    {
        return app(\App\Services\OrdinancePdfService::class)->viewerMode($this);
    }

    public function movBulletinPdfPublicUrl(): ?string
    {
        return app(\App\Services\OrdinancePdfService::class)->publicUrl($this, \App\Support\OrdinancePdfType::BULLETIN);
    }

    public function movBulletinViewerMode(): ?string
    {
        return app(\App\Services\OrdinancePdfService::class)->viewerMode($this, \App\Support\OrdinancePdfType::BULLETIN);
    }

    public function movCertificationPdfPublicUrl(): ?string
    {
        return app(\App\Services\OrdinancePdfService::class)->publicUrl($this, \App\Support\OrdinancePdfType::CERTIFICATION);
    }

    public function movCertificationViewerMode(): ?string
    {
        return app(\App\Services\OrdinancePdfService::class)->viewerMode($this, \App\Support\OrdinancePdfType::CERTIFICATION);
    }

    public function movNewspaperPdfPublicUrl(): ?string
    {
        return app(\App\Services\OrdinancePdfService::class)->publicUrl($this, \App\Support\OrdinancePdfType::NEWSPAPER);
    }

    public function movNewspaperViewerMode(): ?string
    {
        return app(\App\Services\OrdinancePdfService::class)->viewerMode($this, \App\Support\OrdinancePdfType::NEWSPAPER);
    }

    /**
     * @return list<string>
     */
    public function missingPdfMirrorTypes(): array
    {
        return app(\App\Services\OrdinancePdfService::class)->missingMirrorTypes($this);
    }

    protected static function booted(): void
    {
        static::deleting(function (Ordinance $ordinance): void {
            app(\App\Services\AgendaPublishedOutputService::class)->clearFromDeletedOrdinance($ordinance);
        });
    }
}

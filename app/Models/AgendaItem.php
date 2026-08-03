<?php

namespace App\Models;

use App\Models\Concerns\HasActivityLogs;
use App\Models\Concerns\NavigatesById;
use App\Services\AgendaPdfService;
use App\Support\AgendaDeadline;
use App\Support\AgendaMeasureType;
use App\Support\AgendaPdfSlot;
use App\Support\OrdinanceNumberParser;
use App\Support\Permalink;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AgendaItem extends Model
{
    use HasActivityLogs;
    use NavigatesById;
    use SoftDeletes;

    public const OB_STAGE_UNASSIGNED = 'unassigned';

    public const OB_STAGE_UNFINISHED = 'unfinished';

    public const OB_STAGE_COMMITTEE_REPORT = 'committee_report';

    public const OB_STAGE_RESOLVED = 'resolved';

    public const STATUS_NO_DUE_DATE = 'no_due_date';

    public const STATUS_PENDING = 'pending';

    public const STATUS_DONE = 'done';

    public const STATUS_LAPSED = 'lapsed';

    public const OUTPUT_CONNECTION_LINKED = 'linked';

    public const OUTPUT_CONNECTION_PUBLISHED = 'published';

    protected $fillable = [
        'current_version_no',
        'tracking_no',
        'request_pdf_url',
        'request_pdf_path',
        'date_received',
        'time_received',
        'prescribed_days',
        'due_date',
        'status',
        'days_left_label',
        'sender',
        'title',
        'is_urgent_request',
        'committee_referred',
        'date_of_referral',
        'date_of_committee_meeting',
        'committee_meeting_minutes',
        'outcome',
        'committee_report_url',
        'committee_report_pdf_path',
        'date_passed',
        'date_signed_by_gov',
        'reso_ord_ao_no',
        'reso_ord_ao_series',
        'reso_ord_ao_type',
        'reso_ord_ao_url',
        'reso_ord_ao_pdf_path',
        'resolution_id',
        'ordinance_id',
        'appropriation_ordinance_id',
        'published_at',
        'output_connection_type',
        'resolution_title',
        'journal_url',
        'journal_pdf_path',
        'minutes_url',
        'minutes_pdf_path',
        'remarks',
        'incoming_document_id',
        'created_by',
        'ob_lifecycle_stage',
        'ob_manual_override_at',
        'last_ob_synced_session_id',
    ];

    protected static function booted(): void
    {
        static::saving(function (AgendaItem $item) {
            if (! $item->reso_ord_ao_series) {
                $item->reso_ord_ao_series = AgendaDeadline::inferSeries(
                    $item->date_passed,
                    $item->date_signed_by_gov,
                    $item->date_received,
                );
            }

            if ($item->reso_ord_ao_type === '') {
                $item->reso_ord_ao_type = null;
            }

            AgendaDeadline::apply($item);
        });
    }

    protected function casts(): array
    {
        return [
            'date_received' => 'date',
            'due_date' => 'date',
            'date_of_referral' => 'date',
            'date_of_committee_meeting' => 'date',
            'date_passed' => 'date',
            'date_signed_by_gov' => 'date',
            'prescribed_days' => 'integer',
            'reso_ord_ao_series' => 'integer',
            'current_version_no' => 'integer',
            'is_urgent_request' => 'boolean',
            'published_at' => 'datetime',
            'ob_manual_override_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function incomingDocument(): BelongsTo
    {
        return $this->belongsTo(IncomingDocument::class);
    }

    public function resolution(): BelongsTo
    {
        return $this->belongsTo(Resolution::class);
    }

    public function ordinance(): BelongsTo
    {
        return $this->belongsTo(Ordinance::class);
    }

    public function appropriationOrdinance(): BelongsTo
    {
        return $this->belongsTo(AppropriationOrdinance::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function archiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function obBlocks(): HasMany
    {
        return $this->hasMany(ObBlock::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AgendaItemVersion::class)->orderByDesc('version_no');
    }

    public function obPlacements(): HasMany
    {
        return $this->hasMany(AgendaObPlacement::class)->orderByDesc('created_at');
    }

    public function boardMemberCommitteeReports(): BelongsToMany
    {
        return $this->belongsToMany(
            BoardMemberCommitteeReport::class,
            'board_member_committee_report_agenda_item',
            'agenda_item_id',
            'board_member_committee_report_id',
        );
    }

    public function finalObPlacements(): HasMany
    {
        return $this->obPlacements()
            ->whereHas('obDocument', fn ($query) => $query->where('status', ObDocument::STATUS_FINAL));
    }

    public function lastObSyncedSession(): BelongsTo
    {
        return $this->belongsTo(LegislativeSession::class, 'last_ob_synced_session_id');
    }

    public function hasObManualOverride(): bool
    {
        return $this->ob_manual_override_at !== null;
    }

    public function isObLifecycleResolved(): bool
    {
        return $this->ob_lifecycle_stage === self::OB_STAGE_RESOLVED
            || $this->status === self::STATUS_DONE;
    }

    public function currentVersion(): ?AgendaItemVersion
    {
        if ($this->relationLoaded('versions')) {
            return $this->versions->first();
        }

        return $this->versions()->orderByDesc('version_no')->first();
    }

    public function hasIncoming(): bool
    {
        return $this->incoming_document_id !== null;
    }

    public function displayLabel(): string
    {
        if ($this->tracking_no) {
            return '#'.$this->tracking_no;
        }

        return $this->placeholderLabel();
    }

    public function permalinkYear(): int
    {
        return (int) (
            $this->date_received?->year
            ?: $this->reso_ord_ao_series
            ?: $this->created_at?->year
            ?: now()->year
        );
    }

    public function getRouteKey(): string
    {
        return Permalink::agendaKey($this->permalinkYear(), $this->tracking_no, $this->getKey());
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

        $parsed = Permalink::parseAgendaKey($value);

        if ($parsed === null) {
            return null;
        }

        if (isset($parsed['unnumbered_id'])) {
            return $query->whereKey($parsed['unnumbered_id'])->first();
        }

        return $query
            ->where('tracking_no', $parsed['tracking_no'])
            ->orderByDesc($this->getKeyName())
            ->first();
    }

    public function listNumberLabel(): string
    {
        if ($this->tracking_no) {
            return $this->tracking_no;
        }

        return $this->placeholderLabel();
    }

    /**
     * Calendar year shown under List # (year the agenda was added / received).
     */
    public function listYearLabel(): ?int
    {
        if (! $this->tracking_no) {
            return null;
        }

        return $this->permalinkYear();
    }

    public function placeholderLabel(): string
    {
        return $this->is_urgent_request ? '---' : 'Unnumbered';
    }

    public function resoDisplayLabel(): ?string
    {
        if (! $this->reso_ord_ao_no) {
            return null;
        }

        if ($this->reso_ord_ao_series) {
            return trim($this->reso_ord_ao_no).' / '.$this->reso_ord_ao_series;
        }

        return trim($this->reso_ord_ao_no);
    }

    /**
     * Type-specific field label for the Provincial Output number row.
     */
    public function provincialOutputNumberFieldLabel(): string
    {
        return match ($this->effectiveMeasureType()) {
            AgendaMeasureType::RESOLUTION => 'Resolution No.:',
            AgendaMeasureType::ORDINANCE => 'Ordinance No.:',
            AgendaMeasureType::APPROPRIATION_ORDINANCE => 'AO No.:',
            default => 'Reso./Ord./AO No.',
        };
    }

    /**
     * Display number for Provincial Output from the agenda's own fields.
     * Linked SPLIS documents are shown under Connections, not here.
     */
    public function provincialOutputNumberDisplay(): ?string
    {
        if (! filled($this->reso_ord_ao_no)) {
            return null;
        }

        $no = trim((string) $this->reso_ord_ao_no);

        // Keep imported text like "Ord. No. 22" / "Appro. Ord. No. 51" as-is.
        if (! preg_match('/^\d+$/', $no)) {
            return $no;
        }

        if (str_contains($no, '-')) {
            return $no;
        }

        if ($this->reso_ord_ao_series) {
            return $this->reso_ord_ao_series.'-'.$no;
        }

        return $no;
    }

    public function measureTypeLabel(): string
    {
        return AgendaMeasureType::label($this->effectiveMeasureType());
    }

    public function legacyOutputPdfButtonLabel(): string
    {
        return AgendaMeasureType::legacyPdfButtonLabel($this->resoDisplayLabel());
    }

    public function splisOutputButtonLabel(): string
    {
        return AgendaMeasureType::splisOutputButtonLabel($this->effectiveMeasureType());
    }

    public function daysLeftTone(): string
    {
        return AgendaDeadline::toneForItem($this);
    }

    /** @param Builder<AgendaItem> $query */
    public function scopeExpiringSoon(Builder $query): Builder
    {
        $today = now()->startOfDay();
        $end = now()->addDays(AgendaDeadline::expiringSoonDays())->endOfDay();

        return $query
            ->where('status', self::STATUS_PENDING)
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$today, $end]);
    }

    /** @param Builder<AgendaItem> $query */
    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    /** @param Builder<AgendaItem> $query */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function archive(?User $actor = null): void
    {
        if ($this->isArchived()) {
            return;
        }

        $this->forceFill([
            'archived_at' => now(),
            'archived_by' => $actor?->id,
        ])->save();
    }

    public function restoreFromArchive(): void
    {
        if (! $this->isArchived()) {
            return;
        }

        $this->forceFill([
            'archived_at' => null,
            'archived_by' => null,
        ])->save();
    }

    public function previousInList(): ?static
    {
        return static::query()
            ->notArchived()
            ->where($this->getKeyName(), '>', $this->getKey())
            ->orderBy($this->getKeyName())
            ->first();
    }

    public function nextInList(): ?static
    {
        return static::query()
            ->notArchived()
            ->where($this->getKeyName(), '<', $this->getKey())
            ->orderByDesc($this->getKeyName())
            ->first();
    }

    /** @param Builder<AgendaItem> $query */
    public function scopeDueSoon(Builder $query): Builder
    {
        $today = now()->startOfDay();
        $end = now()->addDays(AgendaDeadline::dueSoonDays())->endOfDay();

        return $query
            ->where('status', self::STATUS_PENDING)
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$today, $end]);
    }

    public function deadlineProgressPercent(): ?int
    {
        return AgendaDeadline::progressPercent($this);
    }

    /**
     * @return list<array{key: string, label: string, state: string, date: ?string, detail: ?string}>
     */
    public function workflowSteps(): array
    {
        $intakeComplete = $this->date_received !== null;
        $committeeComplete = filled($this->committee_referred)
            || filled($this->date_of_referral)
            || filled($this->outcome);
        $outputComplete = filled($this->reso_ord_ao_no)
            || $this->date_passed !== null
            || filled($this->resolution_title);

        $activeKey = match (true) {
            ! $intakeComplete => 'intake',
            ! $committeeComplete => 'committee',
            ! $outputComplete => 'output',
            default => 'output',
        };

        $steps = [
            [
                'key' => 'intake',
                'label' => 'Intake',
                'complete' => $intakeComplete,
                'date' => $this->date_received?->format('M d, Y'),
                'detail' => $this->sender,
            ],
            [
                'key' => 'committee',
                'label' => 'Committee',
                'complete' => $committeeComplete,
                'date' => $this->date_of_referral?->format('M d, Y')
                    ?? $this->date_of_committee_meeting?->format('M d, Y'),
                'detail' => $this->committee_referred ?? $this->outcome,
            ],
            [
                'key' => 'output',
                'label' => 'Provincial Output',
                'complete' => $outputComplete,
                'date' => $this->date_passed?->format('M d, Y')
                    ?? $this->date_signed_by_gov?->format('M d, Y'),
                'detail' => $this->resoDisplayLabel(),
            ],
        ];

        return array_map(function (array $step) use ($activeKey) {
            $step['state'] = $step['complete']
                ? 'complete'
                : ($step['key'] === $activeKey ? 'active' : 'upcoming');

            return $step;
        }, $steps);
    }

    public function hasAnyPdf(): bool
    {
        foreach (AgendaPdfSlot::all() as $slot) {
            if ($this->pdfPublicUrlFor($slot) !== null) {
                return true;
            }
        }

        return false;
    }

    public function pdfPublicUrlFor(string $slot): ?string
    {
        return app(AgendaPdfService::class)->publicUrl($this, $slot);
    }

    public function pdfViewerModeFor(string $slot): ?string
    {
        return app(AgendaPdfService::class)->viewerMode($this, $slot);
    }

    public function hasLocalPdfFor(string $slot): bool
    {
        return app(AgendaPdfService::class)->existsFor($this, $slot);
    }

    /**
     * @return list<string>
     */
    public function missingPdfMirrorSlots(): array
    {
        return app(AgendaPdfService::class)->missingMirrorSlots($this);
    }

    public function outputPdfUrl(): ?string
    {
        if ($this->resolution_id && $this->resolution) {
            return route('resolutions.show', $this->resolution);
        }

        if ($this->ordinance_id && $this->ordinance) {
            return route('ordinances.show', $this->ordinance);
        }

        if ($this->appropriation_ordinance_id && $this->appropriationOrdinance) {
            return route('appropriation-ordinances.show', $this->appropriationOrdinance);
        }

        return $this->reso_ord_ao_url;
    }

    public function publishedTargetLabel(): ?string
    {
        if ($this->resolution_id && $this->resolution()->exists()) {
            return 'Resolution';
        }

        if ($this->ordinance_id && $this->ordinance()->exists()) {
            return 'Ordinance';
        }

        if ($this->appropriation_ordinance_id && $this->appropriationOrdinance()->exists()) {
            return 'Appropriation Ordinance';
        }

        return null;
    }

    public function outputConnectionLabel(): string
    {
        return $this->output_connection_type === self::OUTPUT_CONNECTION_PUBLISHED
            ? 'Published to'
            : 'Linked to';
    }

    public function outputWasPublished(): bool
    {
        return $this->output_connection_type === self::OUTPUT_CONNECTION_PUBLISHED;
    }

    public function outputConnectionKey(): ?string
    {
        if ($this->resolution_id !== null) {
            return AgendaMeasureType::RESOLUTION.':'.$this->resolution_id;
        }

        if ($this->ordinance_id !== null) {
            return AgendaMeasureType::ORDINANCE.':'.$this->ordinance_id;
        }

        if ($this->appropriation_ordinance_id !== null) {
            return AgendaMeasureType::APPROPRIATION_ORDINANCE.':'.$this->appropriation_ordinance_id;
        }

        return null;
    }

    /**
     * Stored measure type, or inferred from a linked SPLIS output / number / title.
     */
    public function effectiveMeasureType(): ?string
    {
        if (filled($this->reso_ord_ao_type)) {
            // Number text wins over a mis-stored measure type.
            if (OrdinanceNumberParser::looksLikeAppropriationOrdinanceReference($this->reso_ord_ao_no)) {
                return AgendaMeasureType::APPROPRIATION_ORDINANCE;
            }

            if ($this->reso_ord_ao_type === AgendaMeasureType::RESOLUTION
                && OrdinanceNumberParser::looksLikeOrdinanceReference($this->reso_ord_ao_no)) {
                return AgendaMeasureType::ORDINANCE;
            }

            return $this->reso_ord_ao_type;
        }

        if ($this->resolution_id) {
            return AgendaMeasureType::RESOLUTION;
        }

        if ($this->ordinance_id) {
            return AgendaMeasureType::ORDINANCE;
        }

        if ($this->appropriation_ordinance_id) {
            return AgendaMeasureType::APPROPRIATION_ORDINANCE;
        }

        return self::inferMeasureType($this->resolution_title, $this->reso_ord_ao_no);
    }

    public function publishedTargetRoute(): ?string
    {
        if ($this->resolution_id && $this->resolution) {
            return route('resolutions.show', $this->resolution, absolute: false);
        }

        if ($this->ordinance_id && $this->ordinance) {
            return route('ordinances.show', $this->ordinance, absolute: false);
        }

        if ($this->appropriation_ordinance_id && $this->appropriationOrdinance) {
            return route('appropriation-ordinances.show', $this->appropriationOrdinance, absolute: false);
        }

        return null;
    }

    public function isPublished(): bool
    {
        return ($this->resolution_id !== null && $this->resolution()->exists())
            || ($this->ordinance_id !== null && $this->ordinance()->exists())
            || ($this->appropriation_ordinance_id !== null && $this->appropriationOrdinance()->exists());
    }

    public function hasProvincialOutputFields(): bool
    {
        return filled($this->reso_ord_ao_no) && (int) $this->reso_ord_ao_series > 0;
    }

    public function needsOutputLink(): bool
    {
        return $this->hasProvincialOutputFields() && ! $this->isPublished();
    }

    public static function inferMeasureType(?string $resolutionTitle, ?string $outputNo = null): ?string
    {
        if (OrdinanceNumberParser::looksLikeAppropriationOrdinanceReference($outputNo)) {
            return AgendaMeasureType::APPROPRIATION_ORDINANCE;
        }

        if (OrdinanceNumberParser::looksLikeOrdinanceReference($outputNo)) {
            return AgendaMeasureType::ORDINANCE;
        }

        $title = strtoupper(trim($resolutionTitle ?? ''));

        if ($title === '') {
            return null;
        }

        if (preg_match('/^RESOLUTION\b/', $title)) {
            return AgendaMeasureType::RESOLUTION;
        }

        if (str_contains($title, 'APPROPRIATION ORDINANCE') && ! str_contains($title, 'RESOLUTION')) {
            return AgendaMeasureType::APPROPRIATION_ORDINANCE;
        }

        if (preg_match('/\bORDINANCE\b/', $title) && ! str_contains($title, 'RESOLUTION')) {
            return AgendaMeasureType::ORDINANCE;
        }

        return null;
    }
}

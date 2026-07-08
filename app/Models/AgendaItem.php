<?php

namespace App\Models;

use App\Support\AgendaDeadline;
use App\Support\AgendaMeasureType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AgendaItem extends Model
{
    use SoftDeletes;

    public const STATUS_NO_DUE_DATE = 'no_due_date';

    public const STATUS_PENDING = 'pending';

    public const STATUS_DONE = 'done';

    public const STATUS_LAPSED = 'lapsed';

    protected $fillable = [
        'current_version_no',
        'tracking_no',
        'request_pdf_url',
        'date_received',
        'time_received',
        'prescribed_days',
        'due_date',
        'status',
        'days_left_label',
        'sender',
        'title',
        'committee_referred',
        'date_of_referral',
        'date_of_committee_meeting',
        'committee_meeting_minutes',
        'outcome',
        'committee_report_url',
        'date_passed',
        'date_signed_by_gov',
        'reso_ord_ao_no',
        'reso_ord_ao_series',
        'reso_ord_ao_type',
        'reso_ord_ao_url',
        'resolution_id',
        'ordinance_id',
        'appropriation_ordinance_id',
        'published_at',
        'resolution_title',
        'journal_url',
        'minutes_url',
        'remarks',
        'incoming_document_id',
        'created_by',
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
            'published_at' => 'datetime',
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

        return 'Agenda #'.$this->id;
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

    public function measureTypeLabel(): string
    {
        return AgendaMeasureType::label($this->reso_ord_ao_type);
    }

    public function legacyOutputPdfButtonLabel(): string
    {
        return AgendaMeasureType::legacyPdfButtonLabel($this->resoDisplayLabel());
    }

    public function splisOutputButtonLabel(): string
    {
        return AgendaMeasureType::splisOutputButtonLabel($this->reso_ord_ao_type);
    }

    public function daysLeftTone(): string
    {
        return AgendaDeadline::toneForItem($this);
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
                'label' => 'Provincial output',
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
        return filled($this->request_pdf_url)
            || filled($this->committee_report_url)
            || filled($this->reso_ord_ao_url)
            || filled($this->journal_url)
            || filled($this->minutes_url);
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
        if ($this->resolution_id) {
            return 'Resolution';
        }

        if ($this->ordinance_id) {
            return 'Ordinance';
        }

        if ($this->appropriation_ordinance_id) {
            return 'Appropriation Ordinance';
        }

        return null;
    }

    public function publishedTargetRoute(): ?string
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

        return null;
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null
            || $this->resolution_id !== null
            || $this->ordinance_id !== null
            || $this->appropriation_ordinance_id !== null;
    }

    public static function inferMeasureType(?string $resolutionTitle): ?string
    {
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

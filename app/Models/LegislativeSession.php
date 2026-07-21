<?php

namespace App\Models;

use App\Services\SessionPdfService;
use App\Support\SessionPdfSlot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class LegislativeSession extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'session_date',
        'session_time',
        'session_number',
        'session_kind',
        'venue',
        'prior_session_id',
        'status',
        'notes',
        'guests',
        'pdf_summary_committee_reports',
        'pdf_summary_committee_reports_path',
        'pdf_committee_reports',
        'pdf_draft_journal',
        'pdf_draft_journal_path',
        'pdf_draft_minutes',
        'pdf_draft_minutes_path',
        'pdf_final_journal',
        'pdf_final_journal_path',
        'pdf_final_minutes',
        'pdf_final_minutes_path',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'guests' => 'array',
        ];
    }

    public function guestsList(): array
    {
        $guests = collect($this->guests ?? [])
            ->filter(fn ($guest) => is_array($guest) && (filled($guest['name'] ?? null) || filled($guest['remarks'] ?? null)))
            ->map(fn (array $guest) => [
                'name' => trim((string) ($guest['name'] ?? '')),
                'remarks' => trim((string) ($guest['remarks'] ?? '')),
            ])
            ->values()
            ->all();

        return $guests !== [] ? $guests : [['name' => '', 'remarks' => '']];
    }

    public function priorSession(): BelongsTo
    {
        return $this->belongsTo(self::class, 'prior_session_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function obDocument(): HasOne
    {
        return $this->hasOne(ObDocument::class);
    }

    public function followUpSessions(): HasMany
    {
        return $this->hasMany(self::class, 'prior_session_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(SessionAttendance::class, 'legislative_session_id');
    }

    public function committeeReportFiles(): HasMany
    {
        return $this->hasMany(LegislativeSessionCommitteeReportFile::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function hasLocalCommitteeReportFiles(): bool
    {
        return $this->relationLoaded('committeeReportFiles')
            ? $this->committeeReportFiles->contains(fn (LegislativeSessionCommitteeReportFile $file) => $file->existsLocally())
            : $this->committeeReportFiles()->whereNotNull('stored_path')->exists();
    }

    public function committeeReportsDriveUrl(): ?string
    {
        $url = trim((string) ($this->pdf_committee_reports ?? ''));

        return $url !== '' ? $url : null;
    }

    protected static function booted(): void
    {
        static::deleting(function (self $session): void {
            if (! $session->isForceDeleting()) {
                return;
            }

            $document = $session->obDocument()->with('blocks')->first();
            if (! $document) {
                return;
            }

            $document->blocks()->delete();
            $document->delete();
        });
    }

    public function displayTitle(): string
    {
        $parts = array_filter([
            $this->session_number,
            $this->session_date?->format('F j, Y'),
        ]);

        return $parts !== [] ? implode(' — ', $parts) : 'Legislative session #'.$this->id;
    }

    public function isPastSessionDate(): bool
    {
        return $this->session_date !== null
            && $this->session_date->lt(now()->startOfDay());
    }

    public function sessionKindLabel(): string
    {
        return config('order_of_business.session_kinds.'.$this->session_kind, $this->session_kind);
    }

    public function statusLabel(): string
    {
        return config('order_of_business.session_statuses.'.$this->status, $this->status);
    }

    public function formattedSessionTime(): ?string
    {
        if (! $this->session_time) {
            return null;
        }

        return \Illuminate\Support\Str::of($this->session_time)->substr(0, 5);
    }

    public function formattedSessionTimeForPrint(): ?string
    {
        if (! $this->session_time) {
            return null;
        }

        $formatted = \Carbon\Carbon::parse($this->session_time)->format('g:i A');

        return str_replace(['AM', 'PM'], ['A.M.', 'P.M.'], $formatted);
    }

    /**
     * @return list<array{field: string, label: string, url: ?string, viewer: ?string, kind: string, mirrored: bool}>
     */
    public function sessionPdfLinkRows(): array
    {
        $pdfs = app(SessionPdfService::class);

        return collect(SessionPdfSlot::all())
            ->map(function (string $slot) use ($pdfs): array {
                $config = SessionPdfSlot::config($slot);

                return [
                    'field' => $config['field'],
                    'label' => $config['label'],
                    'url' => $pdfs->publicUrl($this, $slot),
                    'viewer' => $pdfs->viewerMode($this, $slot),
                    'kind' => $config['kind'],
                    'mirrored' => SessionPdfSlot::isMirrorable($slot) && $pdfs->existsFor($this, $slot),
                ];
            })
            ->values()
            ->all();
    }

    public function hasSessionPdfLinks(): bool
    {
        foreach (array_keys(config('order_of_business.session_pdf_links', [])) as $field) {
            if (filled($this->{$field})) {
                return true;
            }
        }

        if ($this->hasLocalCommitteeReportFiles()) {
            return true;
        }

        foreach (SessionPdfSlot::mirrorable() as $slot) {
            if (app(SessionPdfService::class)->existsFor($this, $slot)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function missingMirrorSessionPdfSlots(): array
    {
        return app(SessionPdfService::class)->missingMirrorSlots($this);
    }

    public function sessionPdfPublicUrl(string $slot): ?string
    {
        return app(SessionPdfService::class)->publicUrl($this, $slot);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithFinalObDocument(Builder $query): Builder
    {
        return $query->whereHas('obDocument', fn (Builder $document) => $document->final());
    }

    /**
     * Sessions Board Members may browse (session must be scheduled).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleToBoardMembers(Builder $query): Builder
    {
        return $query->where('status', 'scheduled');
    }

    public function isVisibleToBoardMembers(): bool
    {
        return $this->status === 'scheduled';
    }
}

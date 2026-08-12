<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BoardMemberCommitteeReport extends Model
{
    protected $fillable = [
        'board_member_id',
        'legislative_session_id',
        'title',
        'pdf_path',
        'original_filename',
        'previous_ob_placements',
        'submitted_by',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'previous_ob_placements' => 'array',
        ];
    }

    public function boardMember(): BelongsTo
    {
        return $this->belongsTo(BoardMember::class);
    }

    public function legislativeSession(): BelongsTo
    {
        return $this->belongsTo(LegislativeSession::class);
    }

    /**
     * True when the submitter reserved this report for the next available session/OB.
     */
    public function isReservedForNextSession(): bool
    {
        return $this->legislative_session_id === null;
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function agendaItems(): BelongsToMany
    {
        return $this->belongsToMany(
            AgendaItem::class,
            'board_member_committee_report_agenda_item',
            'board_member_committee_report_id',
            'agenda_item_id',
        );
    }

    public function sessionFiles(): HasMany
    {
        return $this->hasMany(LegislativeSessionCommitteeReportFile::class);
    }

    /**
     * Session this report targets, or the newest session folder copy when reserved/auto-linked.
     */
    public function resolvedTargetSession(): ?LegislativeSession
    {
        if ($this->legislative_session_id !== null) {
            if ($this->relationLoaded('legislativeSession')) {
                return $this->legislativeSession;
            }

            return $this->legislativeSession()->with('obDocument')->first();
        }

        if ($this->relationLoaded('sessionFiles')) {
            $file = $this->sessionFiles
                ->sortByDesc(fn (LegislativeSessionCommitteeReportFile $file) => $file->id)
                ->first();

            if ($file?->relationLoaded('session')) {
                return $file->session;
            }

            return $file?->session()->with('obDocument')->first();
        }

        $file = $this->sessionFiles()->with(['session.obDocument'])->orderByDesc('id')->first();

        return $file?->session;
    }

    public function targetSessionLabel(): string
    {
        $session = $this->resolvedTargetSession();

        return $session?->displayTitle() ?? 'Next available session / OB';
    }

    /**
     * Board members may not edit/delete once the target session/OB is over or finalized.
     */
    public function isLockedForBoardMemberMutation(): bool
    {
        $session = $this->resolvedTargetSession();

        if ($session === null) {
            return false;
        }

        if ($session->status === 'completed') {
            return true;
        }

        if ($session->hasFinalOrderOfBusiness()) {
            return true;
        }

        if ($session->isPastSessionDate()) {
            return true;
        }

        // On session day, lock after the scheduled start time (when set).
        if (filled($session->session_time)) {
            $startsAt = $session->sessionDateTime();

            if ($startsAt !== null && $startsAt->lte(now())) {
                return true;
            }
        }

        return false;
    }
}

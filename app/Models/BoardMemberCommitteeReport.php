<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BoardMemberCommitteeReport extends Model
{
    protected $fillable = [
        'board_member_id',
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
}

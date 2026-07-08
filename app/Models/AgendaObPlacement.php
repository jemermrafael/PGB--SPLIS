<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgendaObPlacement extends Model
{
    protected $fillable = [
        'agenda_item_id',
        'agenda_item_version_id',
        'ob_block_id',
        'legislative_session_id',
        'ob_document_id',
        'section',
        'section_label',
        'session_agenda_no',
        'placed_by',
    ];

    public function agendaItem(): BelongsTo
    {
        return $this->belongsTo(AgendaItem::class);
    }

    public function agendaItemVersion(): BelongsTo
    {
        return $this->belongsTo(AgendaItemVersion::class);
    }

    public function obBlock(): BelongsTo
    {
        return $this->belongsTo(ObBlock::class);
    }

    public function legislativeSession(): BelongsTo
    {
        return $this->belongsTo(LegislativeSession::class);
    }

    public function obDocument(): BelongsTo
    {
        return $this->belongsTo(ObDocument::class);
    }

    public function placer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'placed_by');
    }

    public function sectionLabel(): string
    {
        return $this->section_label
            ?? config('order_of_business.agenda_sections.'.$this->section, $this->section);
    }
}

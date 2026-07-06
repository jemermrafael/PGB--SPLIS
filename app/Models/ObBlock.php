<?php

namespace App\Models;

use App\Enums\ObBlockType;
use App\Support\ObAgendaSnapshot;
use App\Support\ObRomanNumeral;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObBlock extends Model
{
    protected $fillable = [
        'ob_document_id',
        'type',
        'sort_order',
        'content',
        'agenda_item_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => ObBlockType::class,
            'content' => 'array',
        ];
    }

    public function obDocument(): BelongsTo
    {
        return $this->belongsTo(ObDocument::class);
    }

    public function agendaItem(): BelongsTo
    {
        return $this->belongsTo(AgendaItem::class);
    }

    public function previewText(): string
    {
        return match ($this->type) {
            ObBlockType::Heading, ObBlockType::CommitteeGroup, ObBlockType::SubsectionLabel => (string) ($this->content['text'] ?? ''),
            ObBlockType::RomanSection => trim(
                ObRomanNumeral::display($this->content['numeral'] ?? '')
                .' '.($this->content['title'] ?? $this->content['body'] ?? '')
            ),
            ObBlockType::Paragraph => \Illuminate\Support\Str::limit(strip_tags((string) ($this->content['text'] ?? '')), 120),
            ObBlockType::CommitteeReport => trim(
                ($this->content['row_no'] ?? '').'. '.ObAgendaSnapshot::displayAgendaNosLabel($this->content ?? [])
                .' — '.($this->content['committee_name'] ?? '')
            ),
            ObBlockType::UnfinishedCommittee => (string) ($this->content['committee_name'] ?? 'Committee header'),
            ObBlockType::UnfinishedAgenda, ObBlockType::UnassignedAgenda => trim(
                'Agenda No. '.ObAgendaSnapshot::displayAgendaNo($this->content ?? [])
                .' — '.($this->content['title'] ?? 'Untitled')
            ),
            ObBlockType::ReadingAgenda => trim(
                ($this->content['reading'] ?? '2nd').' reading — Agenda No. '.ObAgendaSnapshot::displayAgendaNo($this->content ?? [])
            ),
            ObBlockType::Announcement => trim(
                ($this->content['column_1'] ?? $this->content['date_received'] ?? '')
                .' — '.($this->content['column_2'] ?? $this->content['title'] ?? 'Untitled')
            ),
            ObBlockType::Adjournment => 'VIII — ADJOURNMENT',
            ObBlockType::AgendaLine => trim(
                'Agenda No. '.ObAgendaSnapshot::displayAgendaNo($this->content ?? [])
                .' — '.($this->content['title'] ?? 'Untitled')
            ),
            ObBlockType::Table => 'Table ('.count($this->content['rows'] ?? []).' rows)',
            ObBlockType::PageBreak => '— Page break —',
        };
    }
}

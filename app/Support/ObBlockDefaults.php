<?php

namespace App\Support;

use App\Enums\ObBlockType;
use App\Models\AgendaItem;

class ObBlockDefaults
{
    /**
     * @return array<string, mixed>
     */
    public static function empty(ObBlockType $type): array
    {
        return match ($type) {
            ObBlockType::Heading, ObBlockType::SubsectionLabel, ObBlockType::CommitteeGroup => ['text' => ''],
            ObBlockType::RomanSection => [
                'numeral' => '',
                'title' => '',
                'body' => '',
                'sub_label' => '',
            ],
            ObBlockType::Paragraph => ['text' => ''],
            ObBlockType::CommitteeReport => [
                'row_no' => null,
                'agenda_no' => '',
                'committee_id' => null,
                'committee_name' => '',
                'chair_name' => '',
                'needs_committee' => false,
            ],
            ObBlockType::UnfinishedCommittee => [
                'committee_name' => '',
                'chair_name' => '',
            ],
            ObBlockType::UnfinishedAgenda, ObBlockType::UnassignedAgenda => [
                'agenda_no' => '',
                'committee_id' => null,
                'committee_name' => '',
                'needs_committee' => true,
                'date_received' => '',
                'prescription' => '',
                'title' => '',
                'referral_note' => '',
            ],
            ObBlockType::ReadingAgenda => [
                'reading' => '2nd',
                'agenda_no' => '',
                'date_received' => '',
                'prescription' => '',
                'title' => '',
                'referral_note' => '',
            ],
            ObBlockType::Announcement => [
                'column_1' => '',
                'column_2' => '',
            ],
            ObBlockType::Adjournment => [],
            ObBlockType::AgendaLine => [
                'session_agenda_no' => null,
                'date_received' => '',
                'prescription' => '',
                'title' => '',
                'referral_note' => '',
                'tracking_no' => '',
            ],
            ObBlockType::Table => [
                'headers' => ['Column 1', 'Column 2'],
                'rows' => [],
            ],
            ObBlockType::PageBreak => [],
        };
    }
}

<?php

namespace App\Enums;

enum ObBlockType: string
{
    case Heading = 'heading';
    case RomanSection = 'roman_section';
    case Paragraph = 'paragraph';
    case SubsectionLabel = 'subsection_label';
    case CommitteeReport = 'committee_report';
    case UnfinishedCommittee = 'unfinished_committee';
    case UnfinishedAgenda = 'unfinished_agenda';
    case ReadingAgenda = 'reading_agenda';
    case UnassignedAgenda = 'unassigned_agenda';
    case Announcement = 'announcement';
    case Adjournment = 'adjournment';
    case AgendaLine = 'agenda_line';
    case CommitteeGroup = 'committee_group';
    case Table = 'table';
    case PageBreak = 'page_break';

    public function label(): string
    {
        return match ($this) {
            self::Heading => 'Heading (legacy)',
            self::RomanSection => 'Section (roman numeral)',
            self::Paragraph => 'Paragraph',
            self::SubsectionLabel => 'Subsection label',
            self::CommitteeReport => 'Committee report',
            self::UnfinishedCommittee => 'Unfinished — committee header',
            self::UnfinishedAgenda => 'Unfinished — agenda item',
            self::ReadingAgenda => 'Reading agenda (2nd/3rd)',
            self::UnassignedAgenda => 'Unassigned agenda',
            self::Announcement => 'Announcement / correspondence',
            self::Adjournment => 'Adjournment',
            self::AgendaLine => 'Agenda line (legacy)',
            self::CommitteeGroup => 'Committee group (legacy)',
            self::Table => 'Table (legacy)',
            self::PageBreak => 'Page break',
        };
    }

    /**
     * @return list<self>
     */
    public static function makerTypes(): array
    {
        return [
            self::RomanSection,
            self::Paragraph,
            self::SubsectionLabel,
            self::UnfinishedCommittee,
            self::ReadingAgenda,
            self::Announcement,
        ];
    }
}

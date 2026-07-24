<?php

namespace Tests\Unit;

use App\Models\BoardMember;
use App\Support\ObAgendaSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObAgendaSnapshotFormattingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_separates_and_normalizes_existing_filed_by_text(): void
    {
        $formatted = ObAgendaSnapshot::formatRegularUnassignedTitle(
            'Proposed Resolution, entitled "A SAMPLE RESOLUTION." (Filed by: Vice Governor Ma. Cristina M. Garcia)',
            'VG Cris',
        );

        $this->assertSame(
            'Proposed Resolution, entitled “A SAMPLE RESOLUTION.”',
            $formatted['title'],
        );
        $this->assertSame(
            '(Filed By: Vice Governor Ma. Cristina M. Garcia)',
            $formatted['filer_note'],
        );
    }

    public function test_it_adds_vg_filer_when_title_does_not_contain_one(): void
    {
        $formatted = ObAgendaSnapshot::formatRegularUnassignedTitle(
            'Proposed Resolution, entitled "A SAMPLE RESOLUTION."',
            'VG Cris',
        );

        $this->assertSame('(Filed By: Vice Governor Ma. Cristina M. Garcia)', $formatted['filer_note']);
    }

    public function test_it_resolves_board_member_sender_to_full_name(): void
    {
        BoardMember::query()->create([
            'name' => 'Roman Harold R. Espeleta, OP',
            'is_active' => true,
        ]);

        $formatted = ObAgendaSnapshot::formatRegularUnassignedTitle(
            'Proposed Resolution, entitled "A SAMPLE RESOLUTION."',
            'BM Espeleta',
        );

        $this->assertSame(
            '(Filed By: Board Member Roman Harold R. Espeleta, OP)',
            $formatted['filer_note'],
        );
    }

    public function test_it_does_not_add_filer_for_pgo_or_municipality(): void
    {
        $pgo = ObAgendaSnapshot::formatRegularUnassignedTitle('Request title', 'PGO');
        $municipality = ObAgendaSnapshot::formatRegularUnassignedTitle('Request title', 'Mariveles');

        $this->assertSame('', $pgo['filer_note']);
        $this->assertSame('', $municipality['filer_note']);
    }

    public function test_regular_referral_is_one_parenthesized_line(): void
    {
        $note = ObAgendaSnapshot::unassignedRegularReferralNoteFromReferral('Education and Culture');

        $this->assertStringStartsWith('(To be referred to SP Committee on Education and Culture', $note);
        $this->assertStringNotContainsString("\n", $note);
        $this->assertStringEndsWith(')', $note);
    }

    public function test_regular_referral_matches_committee_across_ampersand_and_word_and_and_puts_chair_on_new_line(): void
    {
        \App\Models\Committee::query()->create([
            'name' => 'SP Committee on Peace and Order and Public Safety',
            'chair' => 'Board Member Romano L. Del Rosario, MPA',
            'is_active' => true,
        ]);

        $note = ObAgendaSnapshot::unassignedRegularReferralNoteFromReferral('Peace and Order & Public Safety');

        $this->assertSame(
            "(To be referred to SP Committee on Peace and Order & Public Safety,\nChaired by: Board Member Romano L. Del Rosario, MPA)",
            $note,
        );
    }

    public function test_unfinished_row_extracts_filed_by_like_regular_unassigned(): void
    {
        $formatted = ObAgendaSnapshot::enrichUnfinishedRow([
            'title' => 'Municipal Ordinance No. 1, entitled "A SAMPLE ORDINANCE." (Filed by: Board Member Romano L. Del Rosario, MPA)',
            'sender' => 'BM Del Rosario',
        ]);

        $this->assertSame(
            'Municipal Ordinance No. 1, entitled “A SAMPLE ORDINANCE.”',
            $formatted['title'],
        );
        $this->assertSame(
            '(Filed By: Board Member Romano L. Del Rosario, MPA)',
            $formatted['filer_note'],
        );
    }

    public function test_shared_committee_report_links_whole_agenda_nos_label(): void
    {
        $html = ObAgendaSnapshot::displayAgendaNosLabelHtml([
            'agenda_nos' => ['058', '267'],
            'agenda_no_links' => [
                '058' => 'https://example.test/report.pdf',
                '267' => 'https://example.test/report.pdf',
            ],
        ]);

        $this->assertSame(
            '<a href="https://example.test/report.pdf" class="ob-print-link" target="_blank" rel="noopener">Agenda Nos. 058, 267</a>',
            $html,
        );
    }

    public function test_different_committee_report_urls_link_each_agenda_no(): void
    {
        $html = ObAgendaSnapshot::displayAgendaNosLabelHtml([
            'agenda_nos' => ['058', '267'],
            'agenda_no_links' => [
                '058' => 'https://example.test/a.pdf',
                '267' => 'https://example.test/b.pdf',
            ],
        ]);

        $this->assertSame(
            'Agenda Nos. <a href="https://example.test/a.pdf" class="ob-print-link" target="_blank" rel="noopener">058</a>, <a href="https://example.test/b.pdf" class="ob-print-link" target="_blank" rel="noopener">267</a>',
            $html,
        );
    }
}

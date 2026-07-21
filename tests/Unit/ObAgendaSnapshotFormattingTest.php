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
}

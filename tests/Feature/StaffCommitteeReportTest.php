<?php

namespace Tests\Feature;

use App\Enums\CommitteeMembershipRole;
use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\BoardMember;
use App\Models\BoardMemberCommitteeReport;
use App\Models\Committee;
use App\Models\CommitteeMembership;
use App\Models\CommitteeTerm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaffCommitteeReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_encoder_can_open_committee_reports_index_and_search(): void
    {
        $encoder = User::factory()->create(['role' => UserRole::Encoder]);
        [$bmUser, $committee, $term, $boardMember] = $this->linkedBoardMemberWithCommittee();

        $agenda = AgendaItem::query()->create([
            'tracking_no' => '401',
            'title' => 'Housing referral',
            'committee_referred' => $committee->name,
            'status' => AgendaItem::STATUS_PENDING,
            'date_of_referral' => now()->toDateString(),
            'prescribed_days' => 0,
            'created_by' => $bmUser->id,
        ]);

        $report = BoardMemberCommitteeReport::query()->create([
            'board_member_id' => $boardMember->id,
            'title' => 'Housing CR',
            'pdf_path' => 'board-member-committee-reports/1/sample.pdf',
            'original_filename' => 'sample.pdf',
            'previous_ob_placements' => [],
            'submitted_by' => $bmUser->id,
            'submitted_at' => now()->subDay(),
        ]);
        $report->agendaItems()->attach($agenda->id);

        $this->actingAs($encoder)
            ->get(route('committee-reports.index'))
            ->assertOk()
            ->assertSee('Committee Reports')
            ->assertSee('Submit Report');

        $this->actingAs($encoder)
            ->getJson(route('committee-reports.search', [
                'committee_id' => $committee->id,
                'date_from' => now()->subDays(2)->toDateString(),
                'date_to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Housing CR')
            ->assertJsonPath('data.0.can_update', false)
            ->assertJsonPath('data.0.can_delete', false);

        $this->actingAs($encoder)
            ->getJson(route('committee-reports.search', [
                'date_from' => now()->addDay()->toDateString(),
            ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_encoder_can_submit_report_on_behalf_of_chair(): void
    {
        Storage::fake('local');

        $encoder = User::factory()->create(['role' => UserRole::Encoder]);
        [, $committee, , $boardMember] = $this->linkedBoardMemberWithCommittee();

        $agenda = AgendaItem::query()->create([
            'tracking_no' => '501',
            'title' => 'Staff-filed report agenda',
            'committee_referred' => $committee->name,
            'status' => AgendaItem::STATUS_PENDING,
            'date_of_referral' => now()->toDateString(),
            'prescribed_days' => 0,
            'created_by' => $encoder->id,
        ]);

        $pdf = UploadedFile::fake()->create('staff-cr.pdf', 100, 'application/pdf');

        $this->actingAs($encoder)
            ->post(route('committee-reports.store'), [
                'board_member_id' => $boardMember->id,
                'title' => 'Staff submitted CR',
                'pdf' => $pdf,
                'agenda_item_ids' => [$agenda->id],
            ])
            ->assertRedirect(route('committee-reports.index'));

        $report = BoardMemberCommitteeReport::query()->first();
        $this->assertNotNull($report);
        $this->assertSame((int) $boardMember->id, (int) $report->board_member_id);
        $this->assertSame((int) $encoder->id, (int) $report->submitted_by);
        $this->assertSame('Staff submitted CR', $report->title);

        $this->actingAs($encoder)
            ->getJson(route('committee-reports.search'))
            ->assertOk()
            ->assertJsonPath('data.0.can_update', true)
            ->assertJsonPath('data.0.can_delete', true);
    }

    public function test_encoder_cannot_edit_or_delete_board_member_submitted_report(): void
    {
        Storage::fake('local');

        $encoder = User::factory()->create(['role' => UserRole::Encoder]);
        [$bmUser, , , $boardMember] = $this->linkedBoardMemberWithCommittee();

        $path = 'board-member-committee-reports/'.$boardMember->id.'/bm.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4');

        $report = BoardMemberCommitteeReport::query()->create([
            'board_member_id' => $boardMember->id,
            'title' => 'BM own report',
            'pdf_path' => $path,
            'original_filename' => 'bm.pdf',
            'previous_ob_placements' => [],
            'submitted_by' => $bmUser->id,
            'submitted_at' => now(),
        ]);

        $this->actingAs($encoder)
            ->get(route('committee-reports.edit', $report))
            ->assertForbidden();

        $this->actingAs($encoder)
            ->put(route('committee-reports.update', $report), [
                'title' => 'Hacked',
            ])
            ->assertForbidden();

        $this->actingAs($encoder)
            ->delete(route('committee-reports.destroy', $report))
            ->assertForbidden();

        $this->assertDatabaseHas('board_member_committee_reports', [
            'id' => $report->id,
            'title' => 'BM own report',
        ]);
    }

    public function test_municipal_viewer_cannot_open_staff_committee_reports(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::MunicipalViewer]);

        $this->actingAs($viewer)
            ->get(route('committee-reports.index'))
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: Committee, 2: CommitteeTerm, 3: BoardMember}
     */
    protected function linkedBoardMemberWithCommittee(): array
    {
        $term = CommitteeTerm::query()->create([
            'label' => '2025–2028',
            'year_from' => 2025,
            'year_to' => 2028,
            'is_current' => true,
        ]);

        $boardMember = BoardMember::query()->create([
            'name' => 'Linked Member',
            'honorific' => 'Hon.',
            'is_active' => true,
        ]);

        $committee = Committee::query()->create([
            'name' => 'Housing and Land Use',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        CommitteeMembership::query()->create([
            'committee_id' => $committee->id,
            'board_member_id' => $boardMember->id,
            'committee_term_id' => $term->id,
            'role' => CommitteeMembershipRole::Chair,
            'sort_order' => 0,
        ]);

        $user = User::factory()->create([
            'role' => UserRole::BoardMember,
            'board_member_id' => $boardMember->id,
            'username' => 'bm_staff_cr_'.uniqid(),
            'is_active' => true,
            'name' => 'Hon. Linked Member',
        ]);

        return [$user, $committee, $term, $boardMember];
    }
}

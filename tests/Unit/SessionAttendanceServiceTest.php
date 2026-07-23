<?php

namespace Tests\Unit;

use App\Models\BoardMember;
use App\Models\BoardMemberTerm;
use App\Models\CommitteeTerm;
use App\Models\LegislativeSession;
use App\Models\SessionAttendance;
use App\Models\User;
use App\Services\SessionAttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionAttendanceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_summary_includes_per_session_attendance(): void
    {
        $user = User::factory()->create();
        $term = CommitteeTerm::currentOrCreate();

        $member = BoardMember::query()->create([
            'name' => 'Jane Doe',
            'district' => '1st District',
            'is_active' => true,
        ]);

        BoardMemberTerm::query()->create([
            'board_member_id' => $member->id,
            'committee_term_id' => $term->id,
            'district' => '1st District',
            'is_active' => true,
        ]);

        $sessionOne = LegislativeSession::query()->create([
            'session_date' => '2026-03-05',
            'session_number' => '1st Regular Session',
            'created_by' => $user->id,
        ]);

        $sessionTwo = LegislativeSession::query()->create([
            'session_date' => '2026-03-19',
            'session_number' => '2nd Regular Session',
            'created_by' => $user->id,
        ]);

        SessionAttendance::query()->create([
            'legislative_session_id' => $sessionOne->id,
            'board_member_id' => $member->id,
            'is_present' => true,
            'recorded_by' => $user->id,
        ]);

        SessionAttendance::query()->create([
            'legislative_session_id' => $sessionTwo->id,
            'board_member_id' => $member->id,
            'is_present' => false,
            'recorded_by' => $user->id,
        ]);

        $summary = app(SessionAttendanceService::class)->monthlySummary(2026, 3);
        $row = collect($summary)->first(fn (array $entry) => $entry['member']->id === $member->id);

        $this->assertNotNull($row);
        $this->assertSame(1, $row['present']);
        $this->assertSame(2, $row['total']);
        $this->assertSame(SessionAttendance::STATUS_PRESENT, $row['sessions'][$sessionOne->id]);
        $this->assertSame(SessionAttendance::STATUS_ABSENT, $row['sessions'][$sessionTwo->id]);
    }

    public function test_monthly_summary_excludes_board_members_outside_current_term(): void
    {
        $user = User::factory()->create();
        $currentTerm = CommitteeTerm::currentOrCreate();

        CommitteeTerm::query()
            ->whereKeyNot($currentTerm->id)
            ->update(['is_current' => false]);

        $priorTerm = CommitteeTerm::query()->create([
            'label' => '2022-2025',
            'year_from' => 2022,
            'year_to' => 2025,
            'is_current' => false,
        ]);

        $currentMember = BoardMember::query()->create([
            'name' => 'Current Member',
            'district' => '1st District',
            'is_active' => true,
        ]);
        $priorMember = BoardMember::query()->create([
            'name' => 'Prior Member',
            'district' => '1st District',
            'is_active' => true,
        ]);

        BoardMemberTerm::query()->create([
            'board_member_id' => $currentMember->id,
            'committee_term_id' => $currentTerm->id,
            'district' => '1st District',
            'is_active' => true,
        ]);
        BoardMemberTerm::query()->create([
            'board_member_id' => $priorMember->id,
            'committee_term_id' => $priorTerm->id,
            'district' => '1st District',
            'is_active' => true,
        ]);

        LegislativeSession::query()->create([
            'session_date' => '2026-03-05',
            'session_number' => '1st Regular Session',
            'created_by' => $user->id,
        ]);

        $summary = app(SessionAttendanceService::class)->monthlySummary(2026, 3);
        $memberIds = collect($summary)->pluck('member.id')->all();

        $this->assertContains($currentMember->id, $memberIds);
        $this->assertNotContains($priorMember->id, $memberIds);
    }

    public function test_monthly_summary_roster_matches_board_members_sort_order(): void
    {
        $user = User::factory()->create();
        $term = CommitteeTerm::currentOrCreate();

        $banzon = BoardMember::query()->create([
            'name' => 'Jovy Z. Banzon',
            'district' => '1st District',
            'is_active' => true,
        ]);
        $magay = BoardMember::query()->create([
            'name' => 'Feliciano G. Magay, Jr.',
            'district' => '1st District',
            'is_active' => true,
        ]);

        BoardMemberTerm::query()->create([
            'board_member_id' => $banzon->id,
            'committee_term_id' => $term->id,
            'district' => '1st District',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        BoardMemberTerm::query()->create([
            'board_member_id' => $magay->id,
            'committee_term_id' => $term->id,
            'district' => '1st District',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        LegislativeSession::query()->create([
            'session_date' => '2026-03-05',
            'session_number' => '1st Regular Session',
            'created_by' => $user->id,
        ]);

        $names = collect(app(SessionAttendanceService::class)->monthlySummary(2026, 3))
            ->pluck('member.name')
            ->all();

        $this->assertSame(['Jovy Z. Banzon', 'Feliciano G. Magay, Jr.'], $names);
    }

    public function test_monthly_print_payload_groups_roster_and_pads_session_columns(): void
    {
        $user = User::factory()->create();
        $term = CommitteeTerm::currentOrCreate();

        $viceGovernor = BoardMember::query()->create([
            'name' => 'Ma. Cristina M. Garcia',
            'honorific' => 'Hon.',
            'district' => 'Vice Governor',
            'is_active' => true,
        ]);
        BoardMemberTerm::query()->create([
            'board_member_id' => $viceGovernor->id,
            'committee_term_id' => $term->id,
            'district' => 'Vice Governor',
            'is_active' => true,
        ]);

        $firstDistrict = BoardMember::query()->create([
            'name' => 'Jomar L. Gaza, J.D.',
            'honorific' => 'Hon.',
            'district' => '1st District',
            'is_active' => true,
        ]);
        BoardMemberTerm::query()->create([
            'board_member_id' => $firstDistrict->id,
            'committee_term_id' => $term->id,
            'district' => '1st District',
            'is_active' => true,
        ]);

        $exOfficio = BoardMember::query()->create([
            'name' => 'Jovy Z. Banzon',
            'honorific' => 'Hon.',
            'district' => 'Ex Officio',
            'is_active' => true,
        ]);
        BoardMemberTerm::query()->create([
            'board_member_id' => $exOfficio->id,
            'committee_term_id' => $term->id,
            'district' => 'Ex Officio',
            'ex_officio_title' => 'PCL President',
            'is_active' => true,
        ]);

        LegislativeSession::query()->create([
            'session_date' => '2026-06-05',
            'session_number' => '1',
            'session_kind' => 'regular',
            'created_by' => $user->id,
        ]);

        $service = app(SessionAttendanceService::class);
        $payload = $service->monthlyPrintPayload(2026, 6);

        $this->assertSame('ATTENDANCE OF VICE GOVERNOR AND BOARD MEMBERS', $payload['title']);
        $this->assertSame('June 2026', $payload['month_label']);
        $this->assertCount(SessionAttendanceService::PRINT_SESSION_COLUMNS, $payload['sessions']);
        $this->assertSame('RS', $service->sessionColumnCode($payload['sessions'][0]));
        $this->assertSame('5', $service->sessionColumnDay($payload['sessions'][0]));
        $this->assertNull($payload['sessions'][1]);
        $this->assertNull($payload['sessions'][2]);
        $this->assertNull($payload['sessions'][3]);
        $this->assertArrayHasKey('prepared_by', $payload);
        $this->assertSame('DESIREE S. SEVILLA', $payload['prepared_by']['name']);

        $labels = collect($payload['rows'])->pluck('label')->filter()->values()->all();
        $this->assertContains('1st District Board Members', $labels);
        $this->assertContains('Ex Officio Board Member', $labels);

        $exRow = collect($payload['rows'])->first(
            fn (array $row) => ($row['type'] ?? null) === 'member' && str_contains((string) ($row['name'] ?? ''), 'Jovy Z. Banzon')
        );
        $this->assertNotNull($exRow);
        $this->assertSame('(PCL President)', $exRow['subtitle']);

        $vgRow = collect($payload['rows'])->first(
            fn (array $row) => ($row['type'] ?? null) === 'member' && str_contains((string) ($row['name'] ?? ''), 'Ma. Cristina M. Garcia')
        );
        $this->assertNotNull($vgRow);
        $this->assertSame('Provincial Vice-Governor', $vgRow['subtitle']);
    }
}

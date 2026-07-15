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
        $this->assertTrue($row['sessions'][$sessionOne->id]);
        $this->assertFalse($row['sessions'][$sessionTwo->id]);
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
}

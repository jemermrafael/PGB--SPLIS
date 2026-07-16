<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\BoardMember;
use App\Models\Ordinance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoardMemberOrdinanceReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_search_authored_ordinances_for_board_member(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $member = BoardMember::query()->create([
            'name' => 'Jane Doe',
            'honorific' => 'Hon.',
            'is_active' => true,
        ]);

        $ordinance = Ordinance::query()->create([
            'ordinance_no' => 12,
            'series_year' => 2026,
            'subject' => 'Housing support ordinance',
            'date_enacted' => '2026-01-15',
            'date_approved' => '2026-01-20',
        ]);

        $member->ordinances()->attach($ordinance->id, [
            'role' => 'authored_sponsored',
            'sort_order' => 0,
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.board-member-ordinances.search', [
                'board_member_id' => $member->id,
            ]))
            ->assertOk()
            ->assertJsonPath('member.name', 'Hon. Jane Doe')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.subject', 'Housing support ordinance')
            ->assertJsonPath('data.0.number_label', 'Ord. No. 12')
            ->assertJsonPath('data.0.series_label', 'Series of 2026')
            ->assertJsonPath('data.0.status', 'passed');
    }

    public function test_encoder_cannot_access_board_member_ordinance_search(): void
    {
        $encoder = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);

        $this->actingAs($encoder)
            ->getJson(route('admin.board-member-ordinances.search', [
                'board_member_id' => 1,
            ]))
            ->assertForbidden();
    }
}

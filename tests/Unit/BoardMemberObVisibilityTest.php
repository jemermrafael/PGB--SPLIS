<?php

namespace Tests\Unit;

use App\Models\LegislativeSession;
use App\Models\ObDocument;
use App\Models\User;
use App\Policies\LegislativeSessionPolicy;
use App\Policies\ObDocumentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoardMemberObVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_board_member_can_only_view_scheduled_sessions(): void
    {
        $user = User::factory()->create(['role' => 'board_member']);
        $policy = new LegislativeSessionPolicy;

        $scheduled = new LegislativeSession(['status' => 'scheduled']);
        $draft = new LegislativeSession(['status' => 'draft']);
        $completed = new LegislativeSession(['status' => 'completed']);

        $this->assertTrue($policy->view($user, $scheduled));
        $this->assertFalse($policy->view($user, $draft));
        $this->assertFalse($policy->view($user, $completed));
    }

    public function test_board_member_can_only_view_ob_document_for_scheduled_session(): void
    {
        $user = User::factory()->create(['role' => 'board_member']);
        $policy = new ObDocumentPolicy;

        $scheduled = LegislativeSession::query()->create([
            'session_date' => '2026-07-20',
            'session_kind' => 'regular',
            'status' => 'scheduled',
        ]);
        $draft = LegislativeSession::query()->create([
            'session_date' => '2026-07-27',
            'session_kind' => 'regular',
            'status' => 'draft',
        ]);

        $scheduledDoc = ObDocument::query()->create([
            'legislative_session_id' => $scheduled->id,
            'title' => 'OB scheduled',
            'status' => 'draft',
        ]);
        $draftDoc = ObDocument::query()->create([
            'legislative_session_id' => $draft->id,
            'title' => 'OB draft',
            'status' => 'final',
        ]);

        $this->assertTrue($policy->view($user, $scheduledDoc->load('legislativeSession')));
        $this->assertFalse($policy->view($user, $draftDoc->load('legislativeSession')));
    }
}

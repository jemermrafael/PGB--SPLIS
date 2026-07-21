<?php

namespace Tests\Unit;

use App\Models\BoardMember;
use App\Models\LegislativeSession;
use App\Models\ObDocument;
use App\Models\User;
use App\Models\UserNotification;
use App\Policies\LegislativeSessionPolicy;
use App\Policies\ObDocumentPolicy;
use App\Services\BoardMemberNotifier;
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
        $scheduled->setRelation('obDocument', new ObDocument(['status' => 'final']));

        $scheduledDraftOb = new LegislativeSession(['status' => 'scheduled']);
        $scheduledDraftOb->setRelation('obDocument', new ObDocument(['status' => 'draft']));

        $completed = new LegislativeSession(['status' => 'completed']);
        $completed->setRelation('obDocument', new ObDocument(['status' => 'final']));

        $draft = new LegislativeSession(['status' => 'draft']);
        $draft->setRelation('obDocument', new ObDocument(['status' => 'final']));

        $this->assertTrue($policy->view($user, $scheduled));
        $this->assertFalse($policy->view($user, $scheduledDraftOb));
        $this->assertTrue($policy->view($user, $completed));
        $this->assertFalse($policy->view($user, $draft));
    }

    public function test_board_member_can_only_view_ob_document_for_visible_session(): void
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
            'status' => 'final',
        ]);
        $draftDoc = ObDocument::query()->create([
            'legislative_session_id' => $draft->id,
            'title' => 'OB draft',
            'status' => 'final',
        ]);
        $completed = LegislativeSession::query()->create([
            'session_date' => '2026-08-03',
            'session_kind' => 'regular',
            'status' => 'completed',
        ]);
        $completedDoc = ObDocument::query()->create([
            'legislative_session_id' => $completed->id,
            'title' => 'OB completed',
            'status' => 'final',
        ]);

        $this->assertTrue($policy->view($user, $scheduledDoc->load('legislativeSession')));
        $this->assertFalse($policy->view($user, $draftDoc->load('legislativeSession')));
        $this->assertTrue($policy->view($user, $completedDoc->load('legislativeSession')));
    }

    public function test_session_created_notification_is_only_created_for_scheduled_session_with_final_ob(): void
    {
        $user = $this->createBoardMemberUser();

        $scheduledDraftOb = LegislativeSession::query()->create([
            'session_date' => '2026-07-27',
            'session_kind' => 'regular',
            'status' => 'scheduled',
        ]);
        ObDocument::query()->create([
            'legislative_session_id' => $scheduledDraftOb->id,
            'title' => 'OB draft',
            'status' => 'draft',
        ]);

        app(BoardMemberNotifier::class)->notifySessionCreated($scheduledDraftOb->fresh('obDocument'));

        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $user->id,
            'legislative_session_id' => $scheduledDraftOb->id,
            'type' => UserNotification::TYPE_SESSION_CREATED,
        ]);

        $scheduledFinalOb = LegislativeSession::query()->create([
            'session_date' => '2026-08-03',
            'session_kind' => 'regular',
            'status' => 'scheduled',
        ]);
        ObDocument::query()->create([
            'legislative_session_id' => $scheduledFinalOb->id,
            'title' => 'OB final',
            'status' => 'final',
        ]);

        app(BoardMemberNotifier::class)->notifySessionCreated($scheduledFinalOb->fresh('obDocument'));

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $user->id,
            'legislative_session_id' => $scheduledFinalOb->id,
            'type' => UserNotification::TYPE_SESSION_CREATED,
            'title' => 'New Session scheduled',
        ]);
    }

    public function test_board_member_notification_query_hides_session_created_notification_when_session_is_not_currently_notifiable(): void
    {
        $user = $this->createBoardMemberUser();

        $scheduled = LegislativeSession::query()->create([
            'session_date' => '2026-07-27',
            'session_kind' => 'regular',
            'status' => 'scheduled',
        ]);
        ObDocument::query()->create([
            'legislative_session_id' => $scheduled->id,
            'title' => 'OB final',
            'status' => 'final',
        ]);

        $notification = UserNotification::query()->create([
            'user_id' => $user->id,
            'legislative_session_id' => $scheduled->id,
            'type' => UserNotification::TYPE_SESSION_CREATED,
            'title' => 'New Session scheduled',
            'body' => $scheduled->displayTitle(),
            'link' => '/order-of-business/'.$scheduled->id,
        ]);

        $this->assertTrue(
            UserNotification::query()
                ->whereKey($notification->id)
                ->visibleToRecipient($user)
                ->exists()
        );

        $scheduled->update(['status' => 'completed']);

        $this->assertFalse(
            UserNotification::query()
                ->whereKey($notification->id)
                ->visibleToRecipient($user)
                ->exists()
        );
    }

    protected function createBoardMemberUser(): User
    {
        $boardMember = BoardMember::query()->create([
            'name' => 'Linked Board Member',
            'honorific' => 'Hon.',
            'is_active' => true,
        ]);

        return User::factory()->create([
            'role' => 'board_member',
            'board_member_id' => $boardMember->id,
            'is_active' => true,
        ]);
    }
}

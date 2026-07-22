<?php

namespace Tests\Unit;

use App\Models\BoardMember;
use App\Models\LegislativeSession;
use App\Models\ObDocument;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\BoardMemberNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoardMemberObCreatedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ob_document_created_notification_is_not_emitted_for_draft_session(): void
    {
        $user = $this->createBoardMemberUser();

        $session = LegislativeSession::query()->create([
            'session_date' => '2026-07-27',
            'session_kind' => 'regular',
            'status' => 'draft',
        ]);
        $document = ObDocument::query()->create([
            'legislative_session_id' => $session->id,
            'title' => 'Order of Business — July 27, 2026',
            'status' => 'draft',
        ]);

        app(BoardMemberNotifier::class)->notifyObDocumentCreated($session->fresh('obDocument'), $document);

        $created = UserNotification::query()
            ->where('user_id', $user->id)
            ->where('legislative_session_id', $session->id)
            ->where('type', UserNotification::TYPE_OB_DOCUMENT_CREATED)
            ->exists();

        $this->assertFalse($created);
    }

    public function test_ob_document_created_notification_is_emitted_for_scheduled_final_ob(): void
    {
        $user = $this->createBoardMemberUser();

        $session = LegislativeSession::query()->create([
            'session_date' => '2026-08-03',
            'session_kind' => 'regular',
            'status' => 'scheduled',
        ]);
        $document = ObDocument::query()->create([
            'legislative_session_id' => $session->id,
            'title' => 'Order of Business — August 3, 2026',
            'status' => 'final',
        ]);

        app(BoardMemberNotifier::class)->notifyObDocumentCreated($session->fresh('obDocument'), $document);

        $created = UserNotification::query()
            ->where('user_id', $user->id)
            ->where('legislative_session_id', $session->id)
            ->where('type', UserNotification::TYPE_OB_DOCUMENT_CREATED)
            ->exists();

        $visible = UserNotification::query()
            ->where('user_id', $user->id)
            ->where('type', UserNotification::TYPE_OB_DOCUMENT_CREATED)
            ->visibleToRecipient($user)
            ->exists();

        $this->assertTrue($created);
        $this->assertTrue($visible);
    }

    public function test_session_created_notification_is_hidden_when_not_scheduled_final(): void
    {
        $user = $this->createBoardMemberUser();

        $session = LegislativeSession::query()->create([
            'session_date' => '2026-07-27',
            'session_kind' => 'regular',
            'status' => 'draft',
        ]);
        ObDocument::query()->create([
            'legislative_session_id' => $session->id,
            'title' => 'OB draft',
            'status' => 'draft',
        ]);

        app(BoardMemberNotifier::class)->notifySessionCreated($session->fresh('obDocument'));

        $created = UserNotification::query()
            ->where('user_id', $user->id)
            ->where('type', UserNotification::TYPE_SESSION_CREATED)
            ->exists();

        $this->assertFalse($created);
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

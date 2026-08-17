<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\SystemNotificationMail;
use App\Models\BoardMember;
use App\Models\LegislativeSession;
use App\Models\ObDocument;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\EmailNotificationSettings;
use App\Services\ObDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SessionAndObSeparatedNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $path = app(EmailNotificationSettings::class)->path();
        if (is_file($path)) {
            @unlink($path);
        }

        parent::tearDown();
    }

    public function test_creating_scheduled_session_notifies_session_only_not_ob(): void
    {
        Mail::fake();
        $encoder = User::factory()->create(['role' => UserRole::Admin]);
        $bm = $this->createBoardMemberUser();
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'email' => 'admin-notify@example.com',
            'is_active' => true,
        ]);
        $superadmin = User::factory()->create([
            'role' => UserRole::Superadmin,
            'email' => 'super-notify@example.com',
            'is_active' => true,
        ]);

        $response = $this->actingAs($encoder)->post(route('ob.sessions.store'), [
            'session_date' => '2026-09-01',
            'session_kind' => 'regular',
            'status' => 'scheduled',
            'session_time' => '09:00',
        ]);

        $response->assertRedirect();

        $session = LegislativeSession::query()->whereDate('session_date', '2026-09-01')->first();
        $this->assertNotNull($session);
        $this->assertSame('draft', $session->obDocument?->status);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $bm->id,
            'legislative_session_id' => $session->id,
            'type' => UserNotification::TYPE_SESSION_CREATED,
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $admin->id,
            'legislative_session_id' => $session->id,
            'type' => UserNotification::TYPE_SESSION_CREATED,
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $superadmin->id,
            'legislative_session_id' => $session->id,
            'type' => UserNotification::TYPE_SESSION_CREATED,
        ]);

        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $bm->id,
            'legislative_session_id' => $session->id,
            'type' => UserNotification::TYPE_OB_DOCUMENT_CREATED,
        ]);

        Mail::assertSent(SystemNotificationMail::class);
    }

    public function test_finalizing_ob_on_scheduled_session_notifies_ob_only(): void
    {
        Mail::fake();
        $bm = $this->createBoardMemberUser();
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'email' => 'admin-ob@example.com',
            'is_active' => true,
        ]);

        $session = LegislativeSession::query()->create([
            'session_date' => '2026-09-08',
            'session_kind' => 'regular',
            'status' => 'scheduled',
        ]);
        $document = ObDocument::query()->create([
            'legislative_session_id' => $session->id,
            'title' => 'Order of Business — September 8, 2026',
            'status' => 'draft',
        ]);

        app(ObDocumentService::class)->updateDocument($document, ['status' => 'final']);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $bm->id,
            'legislative_session_id' => $session->id,
            'type' => UserNotification::TYPE_OB_DOCUMENT_CREATED,
            'title' => 'Order of Business published',
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $admin->id,
            'legislative_session_id' => $session->id,
            'type' => UserNotification::TYPE_OB_DOCUMENT_CREATED,
            'title' => 'Order of Business published',
        ]);

        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $bm->id,
            'legislative_session_id' => $session->id,
            'type' => UserNotification::TYPE_SESSION_CREATED,
        ]);

        Mail::assertSent(SystemNotificationMail::class);
    }

    public function test_scheduling_session_with_existing_final_ob_notifies_both(): void
    {
        Mail::fake();
        $encoder = User::factory()->create(['role' => UserRole::Admin]);
        $bm = $this->createBoardMemberUser();

        $session = LegislativeSession::query()->create([
            'session_date' => '2026-09-15',
            'session_kind' => 'regular',
            'status' => 'draft',
            'created_by' => $encoder->id,
        ]);
        ObDocument::query()->create([
            'legislative_session_id' => $session->id,
            'title' => 'Order of Business — September 15, 2026',
            'status' => 'final',
            'created_by' => $encoder->id,
        ]);

        $response = $this->actingAs($encoder)->put(route('ob.sessions.update', $session), [
            'session_date' => '2026-09-15',
            'session_kind' => 'regular',
            'status' => 'scheduled',
            'session_time' => '10:00',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $bm->id,
            'legislative_session_id' => $session->id,
            'type' => UserNotification::TYPE_SESSION_CREATED,
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $bm->id,
            'legislative_session_id' => $session->id,
            'type' => UserNotification::TYPE_OB_DOCUMENT_CREATED,
        ]);
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
            'email' => 'bm-notify@example.com',
        ]);
    }
}

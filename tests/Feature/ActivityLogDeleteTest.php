<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\AgendaItem;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_delete_history_entry(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin]);
        $encoder = User::factory()->create(['role' => UserRole::Encoder]);

        $agenda = AgendaItem::create([
            'title' => 'Test agenda',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'created_by' => $encoder->id,
        ]);

        $this->actingAs($encoder);
        $log = ActivityLogger::log('agenda.created', $agenda, [
            'tracking_no' => 'TRK-1',
            'title' => $agenda->title,
        ]);

        $this->actingAs($superadmin)
            ->from(route('agenda.show', $agenda))
            ->delete(route('activity-logs.destroy', $log))
            ->assertRedirect(route('agenda.show', $agenda))
            ->assertSessionHas('status', 'History entry removed.');

        $this->assertDatabaseMissing('activity_logs', ['id' => $log->id]);
    }

    public function test_admin_cannot_delete_history_entry(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $log = ActivityLog::query()->create([
            'user_id' => $admin->id,
            'action' => 'agenda.created',
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->delete(route('activity-logs.destroy', $log))
            ->assertForbidden();

        $this->assertDatabaseHas('activity_logs', ['id' => $log->id]);
    }

    public function test_deleting_history_removes_linked_admin_notifications(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $log = ActivityLog::query()->create([
            'user_id' => $superadmin->id,
            'action' => 'agenda.created',
            'created_at' => now(),
        ]);

        UserNotification::query()->create([
            'user_id' => $admin->id,
            'type' => UserNotification::TYPE_ACTIVITY_LOG,
            'title' => 'Agenda created',
            'body' => 'Encoder',
            'activity_log_id' => $log->id,
        ]);

        $this->actingAs($superadmin)
            ->delete(route('activity-logs.destroy', $log));

        $this->assertDatabaseMissing('activity_logs', ['id' => $log->id]);
        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $admin->id,
            'activity_log_id' => $log->id,
        ]);
    }
}

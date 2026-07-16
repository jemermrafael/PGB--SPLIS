<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Committee;
use App\Models\Resolution;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolutionTrashTest extends TestCase
{
    use RefreshDatabase;

    public function test_soft_deleted_resolution_remains_viewable_from_notification_link(): void
    {
        $encoder = User::factory()->create(['role' => UserRole::EncoderDelete]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $resolution = Resolution::create([
            'resolution_no' => '42',
            'resolution_title' => 'Trash me',
            'series' => 2026,
            'status' => 'draft',
            'created_by' => $encoder->id,
        ]);

        $this->actingAs($encoder)
            ->delete(route('resolutions.destroy', $resolution))
            ->assertRedirect(route('resolutions.index'));

        $resolution->refresh();
        $this->assertTrue($resolution->trashed());

        $this->actingAs($admin)
            ->get(route('resolutions.show', $resolution))
            ->assertOk()
            ->assertSee('This resolution is in trash')
            ->assertSee('2026-42');
    }

    public function test_trashing_resolution_notifies_admin_with_resolution_number(): void
    {
        $encoder = User::factory()->create(['role' => UserRole::EncoderDelete]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $resolution = Resolution::create([
            'resolution_no' => '15',
            'resolution_title' => 'Notify trash',
            'series' => 2026,
            'status' => 'draft',
            'created_by' => $encoder->id,
        ]);

        $this->actingAs($encoder);
        ActivityLogger::log('resolution.trashed', $resolution, [
            'resolution_no' => $resolution->resolution_no,
            'series' => $resolution->series,
        ]);
        $resolution->delete();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $admin->id,
            'type' => UserNotification::TYPE_ACTIVITY_LOG,
            'title' => 'Resolution 2026-15 moved to trash',
        ]);

        $notification = UserNotification::query()
            ->where('user_id', $admin->id)
            ->where('title', 'Resolution 2026-15 moved to trash')
            ->first();

        $this->assertNotNull($notification);
        $this->assertSame('/resolutions/'.$resolution->id, parse_url((string) $notification->link, PHP_URL_PATH) ?: $notification->link);
    }

    public function test_restore_and_force_delete_flow(): void
    {
        $encoder = User::factory()->create(['role' => UserRole::EncoderDelete, 'is_active' => true]);
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);

        $resolution = Resolution::create([
            'resolution_no' => '99',
            'resolution_title' => 'Lifecycle test',
            'series' => 2026,
            'status' => 'draft',
            'created_by' => $encoder->id,
        ]);

        $this->actingAs($encoder)->delete(route('resolutions.destroy', $resolution));
        $resolution->refresh();
        $this->assertTrue($resolution->trashed());

        $this->actingAs($superadmin)
            ->post(route('resolutions.restore', $resolution))
            ->assertRedirect(route('resolutions.show', $resolution));

        $resolution->refresh();
        $this->assertFalse($resolution->trashed());

        $this->actingAs($encoder)->delete(route('resolutions.destroy', $resolution));

        $this->actingAs($superadmin)
            ->delete(route('resolutions.force-destroy', $resolution))
            ->assertRedirect(route('admin.trash.index', ['type' => 'resolutions']));

        $this->assertNull(Resolution::withTrashed()->find($resolution->id));
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'resolution.deleted',
            'subject_id' => $resolution->id,
        ]);
    }

    public function test_unified_trash_is_superadmin_only_and_lists_soft_deleted_items(): void
    {
        $encoder = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);

        $committee = Committee::create([
            'sort_order' => 1,
            'name' => 'Committee on Trash',
            'is_active' => true,
        ]);
        $committee->delete();

        $this->actingAs($encoder)
            ->get(route('admin.trash.index'))
            ->assertForbidden();

        $this->actingAs($superadmin)
            ->get(route('admin.trash.index', ['type' => 'committees']))
            ->assertOk()
            ->assertSee('Committee on Trash');

        $this->actingAs($superadmin)
            ->post(route('admin.trash.restore', ['type' => 'committees', 'id' => $committee->id]))
            ->assertRedirect(route('admin.trash.index', ['type' => 'committees']));

        $this->assertFalse($committee->fresh()->trashed());
    }
}

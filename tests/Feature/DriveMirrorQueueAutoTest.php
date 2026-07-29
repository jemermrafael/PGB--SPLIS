<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\DriveFileMirrorQueue;
use App\Models\User;
use App\Services\DriveFileMirrorQueueService;
use App\Services\DriveMirrorQueueSettings;
use App\Support\DriveMirrorEntity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DriveMirrorQueueAutoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $path = app(DriveMirrorQueueSettings::class)->path();
        if (is_file($path)) {
            File::delete($path);
        }
    }

    protected function tearDown(): void
    {
        $path = app(DriveMirrorQueueSettings::class)->path();
        if (is_file($path)) {
            File::delete($path);
        }

        parent::tearDown();
    }

    public function test_superadmin_can_start_and_stop_drive_mirror_auto(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);
        $settings = app(DriveMirrorQueueSettings::class);

        $this->assertFalse($settings->isAutoEnabled());

        $this->actingAs($superadmin)
            ->post(route('admin.data-sync.drive-mirror.auto'), ['auto_enabled' => '1'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertTrue($settings->isAutoEnabled());
        $this->assertSame(5, $settings->perMinute());

        $this->actingAs($superadmin)
            ->get(route('admin.data-sync.index'))
            ->assertOk()
            ->assertDontSee('Start auto (5/min)', false)
            ->assertSee('Stop auto (5/min)', false)
            ->assertSee('Auto: 5/min', false);

        $this->actingAs($superadmin)
            ->post(route('admin.data-sync.drive-mirror.auto'), ['auto_enabled' => '0'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertFalse($settings->isAutoEnabled());
    }

    public function test_admin_cannot_toggle_drive_mirror_auto(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $this->actingAs($admin)
            ->post(route('admin.data-sync.drive-mirror.auto'), ['auto_enabled' => '1'])
            ->assertForbidden();
    }

    public function test_data_sync_page_shows_start_auto_when_disabled(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);

        $this->actingAs($superadmin)
            ->get(route('admin.data-sync.index'))
            ->assertOk()
            ->assertSee('Start auto (5/min)', false)
            ->assertSee('Auto: off', false);
    }

    public function test_process_batch_skips_when_lock_held(): void
    {
        $lock = Cache::lock(DriveFileMirrorQueueService::PROCESS_LOCK_KEY, 30);
        $this->assertTrue($lock->get());

        try {
            DriveFileMirrorQueue::query()->create([
                'entity_type' => DriveMirrorEntity::ORDINANCE,
                'entity_id' => 1,
                'document_slot' => 'main',
                'source_url' => 'https://drive.google.com/file/d/example/view',
                'status' => DriveFileMirrorQueue::STATUS_PENDING,
                'queued_at' => now(),
            ]);

            $result = app(DriveFileMirrorQueueService::class)->processBatch(5);

            $this->assertTrue($result['skipped_locked'] ?? false);
            $this->assertSame(0, $result['processed']);
            $this->assertDatabaseHas('drive_file_mirror_queue', [
                'entity_id' => 1,
                'status' => DriveFileMirrorQueue::STATUS_PENDING,
            ]);
        } finally {
            $lock->release();
        }
    }

    public function test_process_batch_reclaims_stuck_processing_items(): void
    {
        DriveFileMirrorQueue::query()->create([
            'entity_type' => DriveMirrorEntity::ORDINANCE,
            'entity_id' => 9,
            'document_slot' => 'main',
            'source_url' => 'https://drive.google.com/file/d/stuck/view',
            'status' => DriveFileMirrorQueue::STATUS_PROCESSING,
            'started_at' => now()->subMinutes(DriveFileMirrorQueueService::STUCK_PROCESSING_MINUTES + 1),
            'queued_at' => now()->subHour(),
            'attempts' => 1,
        ]);

        $reclaimed = app(DriveFileMirrorQueueService::class)->reclaimStuckProcessing();

        $this->assertSame(1, $reclaimed);
        $this->assertDatabaseHas('drive_file_mirror_queue', [
            'entity_id' => 9,
            'status' => DriveFileMirrorQueue::STATUS_PENDING,
        ]);
    }
}

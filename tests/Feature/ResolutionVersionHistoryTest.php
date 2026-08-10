<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\Resolution;
use App\Models\User;
use App\Services\AgendaOutputPublisher;
use App\Services\ResolutionVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ResolutionVersionHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_records_initial_encoding_version(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);

        $this->actingAs($user)
            ->post(route('resolutions.store'), [
                'resolution_no' => '100',
                'resolution_title' => 'Initial resolution title',
                'series' => 2026,
                'status' => 'draft',
            ])
            ->assertRedirect();

        $resolution = Resolution::query()->where('resolution_no', '100')->first();
        $this->assertNotNull($resolution);
        $this->assertSame(1, $resolution->versions()->count());
        $this->assertSame(1, $resolution->current_version_no);
        $this->assertSame('encoded', $resolution->versions()->first()->change_reason);
        $this->assertSame($user->id, $resolution->versions()->first()->created_by);
        $this->assertSame('Initial resolution title', $resolution->versions()->first()->snapshotValue('resolution_title'));
    }

    public function test_title_change_creates_version_with_diff(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);

        $resolution = Resolution::query()->create([
            'resolution_no' => '101',
            'resolution_title' => 'Old title',
            'series' => 2026,
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
        app(ResolutionVersionService::class)->recordInitialVersion($resolution, $user->id);

        $this->actingAs($user)
            ->put(route('resolutions.update', $resolution), [
                'resolution_no' => '101',
                'resolution_title' => 'New title',
                'series' => 2026,
                'status' => 'draft',
            ])
            ->assertRedirect(route('resolutions.show', $resolution));

        $resolution->refresh();
        $versions = $resolution->versions()->reorder()->orderBy('version_no')->get();

        $this->assertCount(2, $versions);
        $this->assertSame('Old title', $versions[0]->snapshotValue('resolution_title'));
        $this->assertSame('New title', $versions[1]->snapshotValue('resolution_title'));
        $this->assertSame('title', $versions[1]->change_reason);
        $this->assertSame(2, $resolution->current_version_no);

        $changes = app(ResolutionVersionService::class)->changedFields($versions[0], $versions[1]);
        $this->assertNotEmpty($changes);
        $this->assertSame('resolution_title', $changes[0]['field']);
    }

    public function test_non_trigger_field_edits_do_not_create_version_but_notify_admins(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $resolution = Resolution::query()->create([
            'resolution_no' => '104',
            'resolution_title' => 'Stable title',
            'series' => 2026,
            'status' => 'draft',
            'sponsored_by' => 'Old sponsor',
            'created_by' => $user->id,
        ]);
        app(ResolutionVersionService::class)->recordInitialVersion($resolution, $user->id);

        $this->actingAs($user)
            ->put(route('resolutions.update', $resolution), [
                'resolution_no' => '104',
                'resolution_title' => 'Stable title',
                'series' => 2026,
                'status' => 'approved',
                'sponsored_by' => 'New sponsor',
                'keyword' => 'UPDATED',
            ])
            ->assertRedirect(route('resolutions.show', $resolution));

        $resolution->refresh();
        $this->assertSame(1, $resolution->versions()->count());
        $this->assertSame(1, $resolution->current_version_no);
        $this->assertSame('approved', $resolution->status);
        $this->assertSame('New sponsor', $resolution->sponsored_by);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'resolution.updated',
            'subject_id' => $resolution->id,
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $admin->id,
            'type' => \App\Models\UserNotification::TYPE_ACTIVITY_LOG,
            'title' => 'Resolution updated',
        ]);
    }

    public function test_pdf_upload_creates_version_and_keeps_previous_file(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);
        $resolution = Resolution::query()->create([
            'resolution_no' => '102',
            'resolution_title' => 'PDF versioning',
            'series' => 2026,
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $oldRelative = 'resolutions/'.$resolution->id.'/versions/old-file.pdf';
        $oldAbsolute = storage_path('app/'.$oldRelative);
        if (! is_dir(dirname($oldAbsolute))) {
            mkdir(dirname($oldAbsolute), 0777, true);
        }
        file_put_contents($oldAbsolute, '%PDF-1.4 old');
        $resolution->update(['pdf_path' => $oldRelative]);
        app(ResolutionVersionService::class)->recordInitialVersion($resolution, $user->id);

        $newUpload = UploadedFile::fake()->create('replacement.pdf', 120, 'application/pdf');

        $this->actingAs($user)
            ->put(route('resolutions.update', $resolution), [
                'resolution_no' => '102',
                'resolution_title' => 'PDF versioning',
                'series' => 2026,
                'status' => 'draft',
                'pdf' => $newUpload,
            ])
            ->assertRedirect(route('resolutions.show', $resolution));

        $resolution->refresh();
        $versions = $resolution->versions()->reorder()->orderBy('version_no')->get();

        $this->assertCount(2, $versions);
        $this->assertSame($oldRelative, $versions[0]->snapshotValue('pdf_path'));
        $this->assertNotSame($oldRelative, $resolution->pdf_path);
        $this->assertSame($resolution->pdf_path, $versions[1]->snapshotValue('pdf_path'));
        $this->assertFileExists($oldAbsolute);
        $this->assertSame('pdf', $versions[1]->change_reason);

        $this->actingAs($user)
            ->get(route('resolutions.versions.file', [
                'resolution' => $resolution,
                'version' => $versions[0],
            ]))
            ->assertOk();

        @unlink($oldAbsolute);
        if (is_string($resolution->pdf_path)) {
            @unlink(storage_path('app/'.$resolution->pdf_path));
        }
    }

    public function test_published_from_agenda_records_version_reason(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);

        $agenda = AgendaItem::query()->create([
            'tracking_no' => '9001',
            'sender' => 'Balanga',
            'title' => 'Agenda title',
            'status' => AgendaItem::STATUS_DONE,
            'reso_ord_ao_type' => 'resolution',
            'reso_ord_ao_no' => '55',
            'reso_ord_ao_series' => 2026,
            'resolution_title' => 'Published resolution title',
            'date_passed' => now()->toDateString(),
            'created_by' => $user->id,
        ]);

        $published = app(AgendaOutputPublisher::class)->publishIfDone($agenda, $user->id);
        $this->assertTrue($published);

        $agenda->refresh();
        $resolution = Resolution::query()->find($agenda->resolution_id);
        $this->assertNotNull($resolution);
        $this->assertSame(1, $resolution->versions()->count());
        $this->assertSame('published_from_agenda', $resolution->versions()->first()->change_reason);
        $this->assertSame($user->id, $resolution->versions()->first()->created_by);
    }

    public function test_show_page_includes_version_history(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);
        $resolution = Resolution::query()->create([
            'resolution_no' => '103',
            'resolution_title' => 'Show history',
            'series' => 2026,
            'status' => 'approved',
            'created_by' => $user->id,
        ]);
        app(ResolutionVersionService::class)->recordInitialVersion($resolution, $user->id);
        $resolution->update(['resolution_title' => 'Updated history title']);
        app(ResolutionVersionService::class)->recordVersionIfChanged(
            $resolution->fresh(),
            ['resolution_title' => 'Show history'],
            $user->id,
        );

        $this->actingAs($user)
            ->get(route('resolutions.show', $resolution))
            ->assertOk()
            ->assertSee('Version History', false)
            ->assertSee('Initial encoding', false)
            ->assertSee('v1', false)
            ->assertSee('Compare versions', false)
            ->assertSee('resolution-version-compare', false);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\AppropriationOrdinance;
use App\Models\User;
use App\Services\AgendaOutputPublisher;
use App\Services\AppropriationOrdinanceVersionService;
use App\Support\AgendaMeasureType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AppropriationOrdinanceVersionHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_records_initial_encoding_version(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);

        $this->actingAs($user)
            ->post(route('appropriation-ordinances.store'), [
                'subject' => 'Initial appropriation title',
                'ordinance_no' => 5,
                'series_year' => 2026,
            ])
            ->assertRedirect();

        $record = AppropriationOrdinance::query()->where('ordinance_no', 5)->where('series_year', 2026)->first();
        $this->assertNotNull($record);
        $this->assertSame(1, $record->versions()->count());
        $this->assertSame(1, $record->current_version_no);
        $this->assertSame('encoded', $record->versions()->first()->change_reason);
        $this->assertSame($user->id, $record->versions()->first()->created_by);
        $this->assertSame('Initial appropriation title', $record->versions()->first()->snapshotValue('subject'));
    }

    public function test_title_change_creates_version_with_diff(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);

        $record = AppropriationOrdinance::query()->create([
            'subject' => 'Old title',
            'ordinance_no' => 6,
            'series_year' => 2026,
            'created_by' => $user->id,
        ]);
        app(AppropriationOrdinanceVersionService::class)->recordInitialVersion($record, $user->id);

        $this->actingAs($user)
            ->put(route('appropriation-ordinances.update', $record), [
                'subject' => 'New title',
                'ordinance_no' => 6,
                'series_year' => 2026,
            ])
            ->assertRedirect(route('appropriation-ordinances.show', $record));

        $record->refresh();
        $versions = $record->versions()->reorder()->orderBy('version_no')->get();

        $this->assertCount(2, $versions);
        $this->assertSame('Old title', $versions[0]->snapshotValue('subject'));
        $this->assertSame('New title', $versions[1]->snapshotValue('subject'));
        $this->assertSame('title', $versions[1]->change_reason);
        $this->assertSame(2, $record->current_version_no);

        $changes = app(AppropriationOrdinanceVersionService::class)->changedFields($versions[0], $versions[1]);
        $this->assertNotEmpty($changes);
        $this->assertSame('subject', $changes[0]['field']);
    }

    public function test_pdf_upload_creates_version_and_keeps_previous_file(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);
        $record = AppropriationOrdinance::query()->create([
            'subject' => 'PDF versioning',
            'ordinance_no' => 7,
            'series_year' => 2026,
            'created_by' => $user->id,
        ]);

        $oldRelative = 'appropriation-ordinances/'.$record->id.'/versions/old-file.pdf';
        $oldAbsolute = storage_path('app/'.$oldRelative);
        if (! is_dir(dirname($oldAbsolute))) {
            mkdir(dirname($oldAbsolute), 0777, true);
        }
        file_put_contents($oldAbsolute, '%PDF-1.4 old');
        $record->update(['pdf_path' => $oldRelative]);
        app(AppropriationOrdinanceVersionService::class)->recordInitialVersion($record, $user->id);

        $newUpload = UploadedFile::fake()->create('replacement.pdf', 120, 'application/pdf');

        $this->actingAs($user)
            ->put(route('appropriation-ordinances.update', $record), [
                'subject' => 'PDF versioning',
                'ordinance_no' => 7,
                'series_year' => 2026,
                'pdf' => $newUpload,
            ])
            ->assertRedirect(route('appropriation-ordinances.show', $record));

        $record->refresh();
        $versions = $record->versions()->reorder()->orderBy('version_no')->get();

        $this->assertCount(2, $versions);
        $this->assertSame($oldRelative, $versions[0]->snapshotValue('pdf_path'));
        $this->assertNotSame($oldRelative, $record->pdf_path);
        $this->assertSame($record->pdf_path, $versions[1]->snapshotValue('pdf_path'));
        $this->assertFileExists($oldAbsolute);
        $this->assertSame('pdf', $versions[1]->change_reason);

        $this->actingAs($user)
            ->get(route('appropriation-ordinances.versions.file', [
                'appropriationOrdinance' => $record,
                'version' => $versions[0],
            ]))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('appropriation-ordinances.show', $record))
            ->assertOk()
            ->assertSee('Version History', false)
            ->assertSee('Compare versions', false);

        @unlink($oldAbsolute);
        if (is_string($record->pdf_path)) {
            @unlink(storage_path('app/'.$record->pdf_path));
        }
    }

    public function test_published_from_agenda_records_version_reason(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);

        $agenda = AgendaItem::query()->create([
            'tracking_no' => '9005',
            'sender' => 'Balanga',
            'title' => 'Agenda title',
            'status' => AgendaItem::STATUS_DONE,
            'reso_ord_ao_no' => '9',
            'reso_ord_ao_series' => 2026,
            'reso_ord_ao_type' => AgendaMeasureType::APPROPRIATION_ORDINANCE,
            'resolution_title' => 'Appropriation from agenda',
            'date_passed' => '2026-05-01',
        ]);

        $this->assertTrue(app(AgendaOutputPublisher::class)->publishIfDone($agenda, $user->id));

        $agenda->refresh();
        $record = $agenda->appropriationOrdinance;
        $this->assertNotNull($record);
        $this->assertSame(1, $record->versions()->count());
        $this->assertSame('published_from_agenda', $record->versions()->first()->change_reason);
        $this->assertSame('Appropriation from agenda', $record->versions()->first()->snapshotValue('subject'));
    }
}

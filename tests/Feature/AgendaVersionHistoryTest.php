<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\User;
use App\Services\AgendaCsvImporter;
use App\Services\AgendaVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AgendaVersionHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_import_records_initial_version(): void
    {
        $csv = $this->writeCsv([
            [' ', 'Request PDF', 'Date Received', 'Sender', 'Title', 'Status', 'Prescribed Dates'],
            ['330', '', '2026-06-01', 'Balanga', 'Imported agenda sample', 'Pending', '30'],
        ]);

        app(AgendaCsvImporter::class)->sync($csv, linksPath: $this->missingLinksPath(), dryRun: false);

        $agenda = AgendaItem::query()->where('tracking_no', '330')->first();
        $this->assertNotNull($agenda);
        $this->assertSame(1, $agenda->versions()->count());
        $this->assertSame(1, $agenda->current_version_no);
        $this->assertSame('imported', $agenda->versions()->first()->change_reason);
    }

    public function test_csv_reimport_resets_version_history_to_imported_v1(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder]);
        $agenda = AgendaItem::query()->create([
            'tracking_no' => '334',
            'sender' => 'Balanga',
            'title' => 'Pre-import title',
            'status' => AgendaItem::STATUS_PENDING,
            'created_by' => $user->id,
        ]);

        $versions = app(AgendaVersionService::class);
        $versions->recordInitialVersion($agenda, $user->id);
        $agenda->update(['title' => 'Edited after encoding']);
        $versions->recordVersionIfChanged(
            $agenda->fresh(),
            ['title' => 'Pre-import title', 'status' => AgendaItem::STATUS_PENDING],
            $user->id,
        );

        $agenda->refresh();
        $this->assertSame(2, $agenda->versions()->count());
        $this->assertSame(2, $agenda->current_version_no);

        $csv = $this->writeCsv([
            [' ', 'Request PDF', 'Date Received', 'Sender', 'Title', 'Status', 'Prescribed Dates'],
            ['334', '', '2026-06-01', 'Balanga', 'CSV reimported title', 'Pending', '30'],
        ]);

        app(AgendaCsvImporter::class)->sync(
            $csv,
            linksPath: $this->missingLinksPath(),
            dryRun: false,
            userId: $user->id,
        );

        $agenda->refresh();
        $this->assertSame('CSV reimported title', $agenda->title);
        $this->assertSame(1, $agenda->versions()->count());
        $this->assertSame(1, $agenda->current_version_no);

        $version = $agenda->versions()->first();
        $this->assertSame('imported', $version->change_reason);
        $this->assertSame(1, $version->version_no);
        $this->assertSame('CSV reimported title', $version->snapshotValue('title'));
        $this->assertSame($user->id, $version->created_by);
    }

    public function test_backfill_creates_versions_for_items_without_history(): void
    {
        $agenda = AgendaItem::query()->create([
            'tracking_no' => '331',
            'sender' => 'Orion',
            'title' => 'No version yet',
            'status' => AgendaItem::STATUS_PENDING,
        ]);

        $this->assertSame(0, $agenda->versions()->count());

        $created = app(AgendaVersionService::class)->backfillMissingInitialVersions();

        $this->assertSame(1, $created);
        $agenda->refresh();
        $this->assertSame(1, $agenda->versions()->count());
        $this->assertSame(1, $agenda->current_version_no);
    }

    public function test_request_pdf_path_change_creates_version_and_keeps_previous_file(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['role' => UserRole::Encoder]);
        $agenda = AgendaItem::query()->create([
            'tracking_no' => '332',
            'sender' => 'Mariveles',
            'title' => 'PDF versioning',
            'status' => AgendaItem::STATUS_PENDING,
            'created_by' => $user->id,
        ]);

        $oldRelative = 'agenda/'.$agenda->id.'/request/old-file.pdf';
        $newRelative = 'agenda/'.$agenda->id.'/request/replacement.pdf';
        Storage::disk('local')->put($oldRelative, '%PDF-1.4 old');
        Storage::disk('local')->put($newRelative, '%PDF-1.4 new');
        $agenda->update(['request_pdf_path' => $oldRelative]);

        $versions = app(AgendaVersionService::class);
        $versions->recordInitialVersion($agenda, $user->id);

        $before = collect(AgendaVersionService::VERSIONED_FIELDS)
            ->mapWithKeys(fn (string $field) => [$field => $agenda->getAttribute($field)])
            ->all();

        $agenda->update(['request_pdf_path' => $newRelative]);
        $versions->recordVersionIfChanged($agenda->fresh(), $before, $user->id);

        $agenda->refresh();
        $versionRows = $agenda->versions()->reorder()->orderBy('version_no')->get();

        $this->assertCount(2, $versionRows);
        $this->assertSame(1, $versionRows[0]->version_no);
        $this->assertSame(2, $versionRows[1]->version_no);
        $this->assertSame($oldRelative, $versionRows[0]->snapshotValue('request_pdf_path'));
        $this->assertSame($newRelative, $agenda->request_pdf_path);
        $this->assertSame($newRelative, $versionRows[1]->snapshotValue('request_pdf_path'));
        $this->assertTrue(Storage::disk('local')->exists($oldRelative));
        $this->assertTrue(Storage::disk('local')->exists($newRelative));

        $this->actingAs($user)
            ->get(route('agenda.versions.file', [
                'agenda' => $agenda,
                'version' => $versionRows[0],
                'slot' => 'request',
            ]))
            ->assertOk();
    }

    public function test_title_change_creates_version(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder]);
        $agenda = AgendaItem::query()->create([
            'tracking_no' => '340',
            'sender' => 'Balanga',
            'title' => 'Original title',
            'status' => AgendaItem::STATUS_PENDING,
            'created_by' => $user->id,
        ]);
        app(AgendaVersionService::class)->recordInitialVersion($agenda, $user->id);

        $this->actingAs($user)
            ->put(route('agenda.update', $agenda), [
                'tracking_no' => '340',
                'sender' => 'Balanga',
                'title' => 'Updated title',
                'status' => AgendaItem::STATUS_PENDING,
            ])
            ->assertRedirect(route('agenda.show', $agenda));

        $agenda->refresh();
        $this->assertSame(2, $agenda->versions()->count());
        $this->assertSame('Updated title', $agenda->versions()->reorder()->orderByDesc('version_no')->first()->snapshotValue('title'));
    }

    public function test_request_pdf_url_change_creates_version(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder]);
        $agenda = AgendaItem::query()->create([
            'tracking_no' => '341',
            'sender' => 'Orion',
            'title' => 'Drive link agenda',
            'status' => AgendaItem::STATUS_PENDING,
            'request_pdf_url' => 'https://drive.google.com/file/d/old',
            'created_by' => $user->id,
        ]);
        app(AgendaVersionService::class)->recordInitialVersion($agenda, $user->id);

        $this->actingAs($user)
            ->put(route('agenda.update', $agenda), [
                'tracking_no' => '341',
                'sender' => 'Orion',
                'title' => 'Drive link agenda',
                'status' => AgendaItem::STATUS_PENDING,
                'request_pdf_url' => 'https://drive.google.com/file/d/new',
            ])
            ->assertRedirect(route('agenda.show', $agenda));

        $this->assertSame(2, $agenda->fresh()->versions()->count());
    }

    public function test_non_title_non_pdf_edits_do_not_create_version(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder]);
        $agenda = AgendaItem::query()->create([
            'tracking_no' => '342',
            'sender' => 'Pilar',
            'title' => 'Stable title',
            'status' => AgendaItem::STATUS_PENDING,
            'remarks' => 'Old remarks',
            'prescribed_days' => 30,
            'created_by' => $user->id,
        ]);
        app(AgendaVersionService::class)->recordInitialVersion($agenda, $user->id);

        $this->actingAs($user)
            ->put(route('agenda.update', $agenda), [
                'tracking_no' => '342',
                'sender' => 'Limay',
                'title' => 'Stable title',
                'status' => AgendaItem::STATUS_PENDING,
                'remarks' => 'New remarks',
                'prescribed_days' => 60,
            ])
            ->assertRedirect(route('agenda.show', $agenda));

        $agenda->refresh();
        $this->assertSame('Limay', $agenda->sender);
        $this->assertSame('New remarks', $agenda->remarks);
        $this->assertSame(60, $agenda->prescribed_days);
        $this->assertSame(1, $agenda->versions()->count());
        $this->assertSame(1, $agenda->current_version_no);
    }

    public function test_agenda_update_notifies_admins_for_non_version_edits(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $agenda = AgendaItem::query()->create([
            'tracking_no' => '343',
            'sender' => 'Pilar',
            'title' => 'Stable title',
            'status' => AgendaItem::STATUS_PENDING,
            'remarks' => 'Old remarks',
            'prescribed_days' => 30,
            'created_by' => $user->id,
        ]);
        app(AgendaVersionService::class)->recordInitialVersion($agenda, $user->id);

        $this->actingAs($user)
            ->put(route('agenda.update', $agenda), [
                'tracking_no' => '343',
                'sender' => 'Limay',
                'title' => 'Stable title',
                'status' => AgendaItem::STATUS_PENDING,
                'remarks' => 'New remarks',
                'prescribed_days' => 60,
            ])
            ->assertRedirect(route('agenda.show', $agenda));

        $this->assertSame(1, $agenda->fresh()->versions()->count());
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'agenda.updated',
            'subject_id' => $agenda->id,
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $admin->id,
            'type' => \App\Models\UserNotification::TYPE_ACTIVITY_LOG,
        ]);
    }

    /**
     * @param  list<list<string>>  $rows
     */
    protected function writeCsv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'agenda-version-csv-');
        $handle = fopen($path, 'w');

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        return $path;
    }

    protected function missingLinksPath(): string
    {
        return sys_get_temp_dir().DIRECTORY_SEPARATOR.'missing-agenda-links-'.uniqid('', true).'.csv';
    }
}

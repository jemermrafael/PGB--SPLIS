<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Ordinance;
use App\Models\User;
use App\Services\OrdinanceVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrdinanceVersionHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_records_initial_version(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder]);

        $this->actingAs($user)
            ->post(route('ordinances.store'), [
                'ordinance_no' => 20,
                'series_year' => 2026,
                'title' => 'Initial title',
                'subject' => 'Subject text',
            ])
            ->assertRedirect(route('ordinances.index'));

        $ordinance = Ordinance::query()->where('ordinance_no', 20)->where('series_year', 2026)->first();
        $this->assertNotNull($ordinance);
        $this->assertSame(1, $ordinance->versions()->count());
        $this->assertSame(1, $ordinance->current_version_no);
        $this->assertSame('encoded', $ordinance->versions()->first()->change_reason);
        $this->assertSame('Initial title', $ordinance->versions()->first()->snapshotValue('title'));
    }

    public function test_title_change_creates_version(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder]);
        $ordinance = Ordinance::query()->create([
            'ordinance_no' => 21,
            'series_year' => 2026,
            'title' => 'Old title',
            'subject' => 'Subject',
        ]);
        app(OrdinanceVersionService::class)->recordInitialVersion($ordinance, $user->id);

        $this->actingAs($user)
            ->put(route('ordinances.update', $ordinance), [
                'ordinance_no' => 21,
                'series_year' => 2026,
                'title' => 'New title',
                'subject' => 'Subject',
            ])
            ->assertRedirect(route('ordinances.show', $ordinance));

        $ordinance->refresh();
        $versions = $ordinance->versions()->reorder()->orderBy('version_no')->get();

        $this->assertCount(2, $versions);
        $this->assertSame('Old title', $versions[0]->snapshotValue('title'));
        $this->assertSame('New title', $versions[1]->snapshotValue('title'));
        $this->assertSame('title', $versions[1]->change_reason);
        $this->assertSame(2, $ordinance->current_version_no);
    }

    public function test_pdf_upload_creates_version_and_keeps_previous_file(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['role' => UserRole::Encoder]);
        $ordinance = Ordinance::query()->create([
            'ordinance_no' => 22,
            'series_year' => 2026,
            'title' => 'PDF versioning',
            'subject' => 'Subject',
        ]);

        $oldRelative = 'ordinances/'.$ordinance->id.'/main/old-file.pdf';
        Storage::disk('local')->put($oldRelative, '%PDF-1.4 old');
        $ordinance->update(['pdf_path' => $oldRelative]);

        app(OrdinanceVersionService::class)->recordInitialVersion($ordinance, $user->id);

        $newUpload = UploadedFile::fake()->create('replacement.pdf', 120, 'application/pdf');

        $this->actingAs($user)
            ->put(route('ordinances.update', $ordinance), [
                'ordinance_no' => 22,
                'series_year' => 2026,
                'title' => 'PDF versioning',
                'subject' => 'Subject',
                'pdf' => $newUpload,
            ])
            ->assertRedirect(route('ordinances.show', $ordinance));

        $ordinance->refresh();
        $versions = $ordinance->versions()->reorder()->orderBy('version_no')->get();

        $this->assertCount(2, $versions);
        $this->assertSame(1, $versions[0]->version_no);
        $this->assertSame(2, $versions[1]->version_no);
        $this->assertSame($oldRelative, $versions[0]->snapshotValue('pdf_path'));
        $this->assertNotSame($oldRelative, $ordinance->pdf_path);
        $this->assertSame($ordinance->pdf_path, $versions[1]->snapshotValue('pdf_path'));
        $this->assertTrue(Storage::disk('local')->exists($oldRelative));
        $this->assertTrue(Storage::disk('local')->exists($ordinance->pdf_path));
        $this->assertSame('pdf', $versions[1]->change_reason);

        $this->actingAs($user)
            ->get(route('ordinances.versions.file', [
                'ordinance' => $ordinance,
                'version' => $versions[0],
                'type' => 'main',
            ]))
            ->assertOk();
    }

    public function test_show_page_includes_version_history(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder]);
        $ordinance = Ordinance::query()->create([
            'ordinance_no' => 23,
            'series_year' => 2026,
            'title' => 'Shown title',
            'subject' => 'Subject',
        ]);
        app(OrdinanceVersionService::class)->recordInitialVersion($ordinance, $user->id);

        $this->actingAs($user)
            ->get(route('ordinances.show', $ordinance))
            ->assertOk()
            ->assertSee('Version History')
            ->assertSee('Shown title')
            ->assertSee('v1');
    }
}

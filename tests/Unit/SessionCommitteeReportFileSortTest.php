<?php

namespace Tests\Unit;

use App\Models\LegislativeSession;
use App\Models\LegislativeSessionCommitteeReportFile;
use App\Services\SessionCommitteeReportFileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SessionCommitteeReportFileSortTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_files_sort_naturally_by_filename(): void
    {
        Storage::fake('local');

        $session = LegislativeSession::query()->create([
            'session_date' => '2026-09-01',
            'session_kind' => 'regular',
            'status' => 'scheduled',
        ]);

        $make = function (string $name, int $sort) use ($session): LegislativeSessionCommitteeReportFile {
            $path = 'order-of-business/'.$session->id.'/committee-reports/'.$name;
            Storage::disk('local')->put($path, '%PDF-1.4');

            return LegislativeSessionCommitteeReportFile::query()->create([
                'legislative_session_id' => $session->id,
                'original_filename' => $name,
                'stored_path' => $path,
                'mime_type' => 'application/pdf',
                'file_size' => 8,
                'sort_order' => $sort,
            ]);
        };

        $make('10 - Tourism.pdf', 1);
        $make('2 - Ways and Means.pdf', 2);
        $make('1 - Agriculture.pdf', 3);

        $sorted = app(SessionCommitteeReportFileService::class)
            ->sortedLocalForDisplay($session->fresh()->committeeReportFiles)
            ->pluck('original_filename')
            ->all();

        $this->assertSame([
            '1 - Agriculture.pdf',
            '2 - Ways and Means.pdf',
            '10 - Tourism.pdf',
        ], $sorted);
    }

    public function test_edit_list_sort_includes_files_without_requiring_local_copy(): void
    {
        $session = LegislativeSession::query()->create([
            'session_date' => '2026-09-02',
            'session_kind' => 'regular',
            'status' => 'scheduled',
        ]);

        LegislativeSessionCommitteeReportFile::query()->create([
            'legislative_session_id' => $session->id,
            'original_filename' => '2.pdf',
            'stored_path' => 'missing/2.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1,
            'sort_order' => 9,
        ]);
        LegislativeSessionCommitteeReportFile::query()->create([
            'legislative_session_id' => $session->id,
            'original_filename' => '1.pdf',
            'stored_path' => 'missing/1.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1,
            'sort_order' => 1,
        ]);

        $sorted = app(SessionCommitteeReportFileService::class)
            ->sortedForDisplay($session->fresh()->committeeReportFiles)
            ->pluck('original_filename')
            ->all();

        $this->assertSame(['1.pdf', '2.pdf'], $sorted);
    }
}

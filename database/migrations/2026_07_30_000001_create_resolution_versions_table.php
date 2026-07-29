<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resolutions', function (Blueprint $table) {
            $table->unsignedInteger('current_version_no')->default(1)->after('resolution_title');
        });

        Schema::create('resolution_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resolution_id')->constrained('resolutions')->cascadeOnDelete();
            $table->unsignedInteger('version_no');
            $table->string('change_reason', 40)->default('general');
            $table->json('snapshot');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['resolution_id', 'version_no'], 'resolution_versions_unique');
            $table->index(['resolution_id', 'created_at']);
        });

        $this->backfillInitialVersions();
    }

    public function down(): void
    {
        Schema::dropIfExists('resolution_versions');

        Schema::table('resolutions', function (Blueprint $table) {
            $table->dropColumn('current_version_no');
        });
    }

    protected function backfillInitialVersions(): void
    {
        if (! Schema::hasTable('resolutions')) {
            return;
        }

        $now = now();
        $publishedFromAgenda = [];

        if (Schema::hasTable('agenda_items')) {
            $publishedFromAgenda = DB::table('agenda_items')
                ->whereNotNull('resolution_id')
                ->pluck('resolution_id')
                ->flip()
                ->all();
        }

        DB::table('resolutions')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($now, $publishedFromAgenda): void {
                $versions = [];

                foreach ($rows as $row) {
                    $reason = 'encoded';

                    if (isset($publishedFromAgenda[$row->id])) {
                        $reason = 'published_from_agenda';
                    } elseif (! empty($row->incoming_document_id)) {
                        $reason = 'published_from_incoming';
                    } elseif (! empty($row->legacy_sp_id)) {
                        $reason = 'imported';
                    }

                    $versions[] = [
                        'resolution_id' => $row->id,
                        'version_no' => 1,
                        'change_reason' => $reason,
                        'snapshot' => json_encode($this->snapshotFromRow($row)),
                        'created_by' => $row->created_by,
                        'created_at' => $row->created_at ?? $now,
                        'updated_at' => $row->updated_at ?? $now,
                    ];
                }

                if ($versions !== []) {
                    DB::table('resolution_versions')->insert($versions);
                }
            });
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshotFromRow(object $row): array
    {
        return [
            'resolution_no' => $row->resolution_no ?? null,
            'resolution_title' => $row->resolution_title ?? null,
            'series' => $row->series ?? null,
            'status' => $row->status ?? null,
            'date_approved' => $row->date_approved ?? null,
            'sponsored_by' => $row->sponsored_by ?? null,
            'department_id' => $row->department_id ?? null,
            'category_id' => $row->category_id ?? null,
            'category2_id' => $row->category2_id ?? null,
            'category3_id' => $row->category3_id ?? null,
            'category4_id' => $row->category4_id ?? null,
            'keyword' => $row->keyword ?? null,
            'committee' => $row->committee ?? null,
            'app_ord_no' => $row->app_ord_no ?? null,
            'amount' => $row->amount ?? null,
            'municipality_id' => $row->municipality_id ?? null,
            'province' => $row->province ?? null,
            'pdf_path' => $row->pdf_path ?? null,
            'sp_pdf_url' => $row->sp_pdf_url ?? null,
            'document_type' => $row->document_type ?? null,
        ];
    }
};

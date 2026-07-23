<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordinances', function (Blueprint $table) {
            $table->unsignedInteger('current_version_no')->default(1)->after('title');
        });

        Schema::create('ordinance_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ordinance_id')->constrained('ordinances')->cascadeOnDelete();
            $table->unsignedInteger('version_no');
            $table->string('change_reason', 40)->default('general');
            $table->json('snapshot');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['ordinance_id', 'version_no'], 'ordinance_versions_unique');
            $table->index(['ordinance_id', 'created_at']);
        });

        $this->backfillInitialVersions();
    }

    public function down(): void
    {
        Schema::dropIfExists('ordinance_versions');

        Schema::table('ordinances', function (Blueprint $table) {
            $table->dropColumn('current_version_no');
        });
    }

    protected function backfillInitialVersions(): void
    {
        if (! Schema::hasTable('ordinances')) {
            return;
        }

        $now = now();

        DB::table('ordinances')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($now): void {
                $versions = [];

                foreach ($rows as $row) {
                    $versions[] = [
                        'ordinance_id' => $row->id,
                        'version_no' => 1,
                        'change_reason' => 'encoded',
                        'snapshot' => json_encode($this->snapshotFromRow($row)),
                        'created_by' => null,
                        'created_at' => $row->created_at ?? $now,
                        'updated_at' => $row->updated_at ?? $now,
                    ];
                }

                if ($versions !== []) {
                    DB::table('ordinance_versions')->insert($versions);
                }
            });
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshotFromRow(object $row): array
    {
        return [
            'title' => $row->title ?? null,
            'pdf_url' => $row->pdf_url ?? null,
            'pdf_path' => $row->pdf_path ?? null,
            'mov_bulletin_url' => $row->mov_bulletin_url ?? null,
            'mov_bulletin_pdf_path' => $row->mov_bulletin_pdf_path ?? null,
            'mov_certification_url' => $row->mov_certification_url ?? null,
            'mov_certification_pdf_path' => $row->mov_certification_pdf_path ?? null,
            'mov_newspaper_url' => $row->mov_newspaper_url ?? null,
            'mov_newspaper_pdf_path' => $row->mov_newspaper_pdf_path ?? null,
        ];
    }
};

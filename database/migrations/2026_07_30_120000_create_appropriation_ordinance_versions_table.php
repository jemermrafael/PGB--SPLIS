<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appropriation_ordinances', function (Blueprint $table) {
            $table->unsignedInteger('current_version_no')->default(1)->after('subject');
        });

        Schema::create('appropriation_ordinance_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('appropriation_ordinance_id');
            $table->unsignedInteger('version_no');
            $table->string('change_reason', 40)->default('general');
            $table->json('snapshot');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('appropriation_ordinance_id', 'ao_versions_ao_id_fk')
                ->references('id')
                ->on('appropriation_ordinances')
                ->cascadeOnDelete();
            $table->unique(['appropriation_ordinance_id', 'version_no'], 'ao_versions_unique');
            $table->index(['appropriation_ordinance_id', 'created_at'], 'ao_versions_created_idx');
        });

        $this->backfillInitialVersions();
    }

    public function down(): void
    {
        Schema::dropIfExists('appropriation_ordinance_versions');

        Schema::table('appropriation_ordinances', function (Blueprint $table) {
            $table->dropColumn('current_version_no');
        });
    }

    protected function backfillInitialVersions(): void
    {
        if (! Schema::hasTable('appropriation_ordinances')) {
            return;
        }

        $now = now();

        DB::table('appropriation_ordinances')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($now): void {
                $versions = [];

                foreach ($rows as $row) {
                    $reason = ! empty($row->agenda_item_id) ? 'published_from_agenda' : 'encoded';

                    $versions[] = [
                        'appropriation_ordinance_id' => $row->id,
                        'version_no' => 1,
                        'change_reason' => $reason,
                        'snapshot' => json_encode($this->snapshotFromRow($row)),
                        'created_by' => $row->created_by,
                        'created_at' => $row->created_at ?? $now,
                        'updated_at' => $row->updated_at ?? $now,
                    ];
                }

                if ($versions !== []) {
                    DB::table('appropriation_ordinance_versions')->insert($versions);
                }
            });
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshotFromRow(object $row): array
    {
        return [
            'subject' => $row->subject,
            'ordinance_no' => $row->ordinance_no,
            'series_year' => $row->series_year,
            'date_received' => $row->date_received,
            'date_passed' => $row->date_passed,
            'date_approved' => $row->date_approved,
            'pdf_url' => $row->pdf_url,
            'pdf_path' => $row->pdf_path,
        ];
    }
};

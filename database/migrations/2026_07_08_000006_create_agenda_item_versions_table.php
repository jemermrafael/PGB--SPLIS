<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agenda_items', function (Blueprint $table) {
            $table->unsignedInteger('current_version_no')->default(1)->after('tracking_no');
        });

        Schema::create('agenda_item_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agenda_item_id')->constrained('agenda_items')->cascadeOnDelete();
            $table->unsignedInteger('version_no');
            $table->string('change_reason', 40)->default('general');
            $table->json('snapshot');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['agenda_item_id', 'version_no'], 'agenda_item_versions_unique');
            $table->index(['agenda_item_id', 'created_at']);
        });

        $this->backfillInitialVersions();
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_item_versions');

        Schema::table('agenda_items', function (Blueprint $table) {
            $table->dropColumn('current_version_no');
        });
    }

    protected function backfillInitialVersions(): void
    {
        if (! Schema::hasTable('agenda_items')) {
            return;
        }

        $now = now();

        DB::table('agenda_items')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($now): void {
                $versions = [];

                foreach ($rows as $row) {
                    $versions[] = [
                        'agenda_item_id' => $row->id,
                        'version_no' => 1,
                        'change_reason' => 'encoded',
                        'snapshot' => json_encode($this->snapshotFromRow($row)),
                        'created_by' => $row->created_by,
                        'created_at' => $row->created_at ?? $now,
                        'updated_at' => $row->updated_at ?? $now,
                    ];
                }

                if ($versions !== []) {
                    DB::table('agenda_item_versions')->insert($versions);
                }
            });
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshotFromRow(object $row): array
    {
        return [
            'tracking_no' => $row->tracking_no,
            'request_pdf_url' => $row->request_pdf_url,
            'date_received' => $row->date_received,
            'time_received' => $row->time_received,
            'prescribed_days' => $row->prescribed_days,
            'due_date' => $row->due_date,
            'status' => $row->status,
            'sender' => $row->sender,
            'title' => $row->title,
            'committee_referred' => $row->committee_referred,
            'date_of_referral' => $row->date_of_referral,
            'date_of_committee_meeting' => $row->date_of_committee_meeting,
            'committee_meeting_minutes' => $row->committee_meeting_minutes,
            'outcome' => $row->outcome,
            'committee_report_url' => $row->committee_report_url,
            'date_passed' => $row->date_passed,
            'date_signed_by_gov' => $row->date_signed_by_gov,
            'reso_ord_ao_no' => $row->reso_ord_ao_no,
            'reso_ord_ao_series' => $row->reso_ord_ao_series,
            'reso_ord_ao_type' => $row->reso_ord_ao_type,
            'reso_ord_ao_url' => $row->reso_ord_ao_url,
            'resolution_title' => $row->resolution_title,
            'journal_url' => $row->journal_url,
            'minutes_url' => $row->minutes_url,
            'remarks' => $row->remarks,
        ];
    }
};

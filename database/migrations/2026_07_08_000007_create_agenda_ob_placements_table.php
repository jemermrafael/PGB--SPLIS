<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agenda_ob_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agenda_item_id')->constrained('agenda_items')->cascadeOnDelete();
            $table->foreignId('agenda_item_version_id')->nullable()->constrained('agenda_item_versions')->nullOnDelete();
            $table->foreignId('ob_block_id')->constrained('ob_blocks')->cascadeOnDelete();
            $table->foreignId('legislative_session_id')->constrained('legislative_sessions')->cascadeOnDelete();
            $table->foreignId('ob_document_id')->constrained('ob_documents')->cascadeOnDelete();
            $table->string('section', 40);
            $table->string('section_label', 120)->nullable();
            $table->string('session_agenda_no', 20)->nullable();
            $table->foreignId('placed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['ob_block_id', 'agenda_item_id'], 'agenda_ob_placements_block_agenda_unique');
            $table->index(['agenda_item_id', 'created_at']);
        });

        $this->backfillFromObBlocks();
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_ob_placements');
    }

    protected function backfillFromObBlocks(): void
    {
        if (! Schema::hasTable('ob_blocks')) {
            return;
        }

        $sections = config('order_of_business.agenda_sections', []);
        $now = now();

        DB::table('ob_blocks')
            ->whereNotNull('agenda_item_id')
            ->orderBy('id')
            ->chunkById(200, function ($blocks) use ($sections, $now): void {
                $placements = [];

                foreach ($blocks as $block) {
                    $content = json_decode((string) $block->content, true) ?? [];
                    $document = DB::table('ob_documents')->where('id', $block->ob_document_id)->first();

                    if ($document === null) {
                        continue;
                    }

                    $sessionAgendaNo = $content['session_agenda_no']
                        ?? $content['agenda_no']
                        ?? null;

                    $section = $this->inferSectionFromBlockType((string) $block->type);

                    $placements[] = [
                        'agenda_item_id' => $block->agenda_item_id,
                        'agenda_item_version_id' => DB::table('agenda_item_versions')
                            ->where('agenda_item_id', $block->agenda_item_id)
                            ->orderByDesc('version_no')
                            ->value('id'),
                        'ob_block_id' => $block->id,
                        'legislative_session_id' => $document->legislative_session_id,
                        'ob_document_id' => $block->ob_document_id,
                        'section' => $section,
                        'section_label' => $sections[$section] ?? $section,
                        'session_agenda_no' => $sessionAgendaNo !== null ? (string) $sessionAgendaNo : null,
                        'placed_by' => null,
                        'created_at' => $block->created_at ?? $now,
                        'updated_at' => $block->updated_at ?? $now,
                    ];
                }

                if ($placements !== []) {
                    DB::table('agenda_ob_placements')->insert($placements);
                }
            });
    }

    protected function inferSectionFromBlockType(string $type): string
    {
        return match ($type) {
            'committee_report' => 'committee_reports',
            'unfinished_agenda' => 'unfinished',
            'reading_agenda' => 'business_2nd',
            'unassigned_agenda' => 'unassigned_regular',
            default => 'unassigned_regular',
        };
    }
};

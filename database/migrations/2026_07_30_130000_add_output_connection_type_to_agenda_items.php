<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agenda_items', function (Blueprint $table) {
            $table->string('output_connection_type', 20)->nullable()->after('published_at');
        });

        DB::table('agenda_items')
            ->where(function ($query): void {
                $query->whereNotNull('resolution_id')
                    ->orWhereNotNull('ordinance_id')
                    ->orWhereNotNull('appropriation_ordinance_id');
            })
            ->update(['output_connection_type' => 'linked']);

        $this->markOutputsCreatedFromAgendaAsPublished();
    }

    public function down(): void
    {
        Schema::table('agenda_items', function (Blueprint $table) {
            $table->dropColumn('output_connection_type');
        });
    }

    protected function markOutputsCreatedFromAgendaAsPublished(): void
    {
        DB::table('agenda_items')
            ->whereNotNull('published_at')
            ->where(function ($query): void {
                $query->whereNotNull('resolution_id')
                    ->orWhereNotNull('ordinance_id')
                    ->orWhereNotNull('appropriation_ordinance_id');
            })
            ->orderBy('id')
            ->chunkById(200, function ($agendas): void {
                foreach ($agendas as $agenda) {
                    $target = match (true) {
                        $agenda->resolution_id !== null => DB::table('resolutions')->find($agenda->resolution_id),
                        $agenda->ordinance_id !== null => DB::table('ordinances')->find($agenda->ordinance_id),
                        $agenda->appropriation_ordinance_id !== null => DB::table('appropriation_ordinances')->find($agenda->appropriation_ordinance_id),
                        default => null,
                    };

                    if ($target === null || $target->created_at === null) {
                        continue;
                    }

                    $createdAt = Carbon::parse($target->created_at);
                    $connectedAt = Carbon::parse($agenda->published_at);

                    if (abs($createdAt->diffInSeconds($connectedAt, false)) > 5) {
                        continue;
                    }

                    DB::table('agenda_items')
                        ->where('id', $agenda->id)
                        ->update(['output_connection_type' => 'published']);
                }
            });
    }
};

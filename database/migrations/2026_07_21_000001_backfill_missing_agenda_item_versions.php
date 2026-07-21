<?php

use App\Services\AgendaVersionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('agenda_items') || ! Schema::hasTable('agenda_item_versions')) {
            return;
        }

        app(AgendaVersionService::class)->backfillMissingInitialVersions();
    }

    public function down(): void
    {
        // Intentionally empty — version history backfill is not reversed.
    }
};

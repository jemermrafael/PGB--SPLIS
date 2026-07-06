<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('sptrack_import_queue');
    }

    public function down(): void
    {
        // Queue removed — restore via 2026_06_24_000008 and 2026_06_26_000012 if needed.
    }
};

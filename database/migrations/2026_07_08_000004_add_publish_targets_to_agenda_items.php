<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agenda_items', function (Blueprint $table) {
            $table->foreignId('ordinance_id')
                ->nullable()
                ->after('resolution_id')
                ->constrained('ordinances')
                ->nullOnDelete();

            $table->foreignId('appropriation_ordinance_id')
                ->nullable()
                ->after('ordinance_id')
                ->constrained('appropriation_ordinances')
                ->nullOnDelete();

            $table->timestamp('published_at')->nullable()->after('appropriation_ordinance_id');
        });
    }

    public function down(): void
    {
        Schema::table('agenda_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('appropriation_ordinance_id');
            $table->dropConstrainedForeignId('ordinance_id');
            $table->dropColumn('published_at');
        });
    }
};

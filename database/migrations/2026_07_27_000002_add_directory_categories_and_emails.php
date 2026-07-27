<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('directory_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('directory_entries', function (Blueprint $table) {
            $table->foreignId('directory_category_id')
                ->nullable()
                ->after('id')
                ->constrained('directory_categories')
                ->nullOnDelete();
            $table->json('emails')->nullable()->after('email');
        });

        $entries = DB::table('directory_entries')
            ->select('id', 'email')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get();

        foreach ($entries as $entry) {
            DB::table('directory_entries')
                ->where('id', $entry->id)
                ->update([
                    'emails' => json_encode([trim((string) $entry->email)]),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('directory_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('directory_category_id');
            $table->dropColumn('emails');
        });

        Schema::dropIfExists('directory_categories');
    }
};

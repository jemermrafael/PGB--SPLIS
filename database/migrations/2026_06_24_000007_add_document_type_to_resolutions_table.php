<?php

use App\Models\Resolution;
use App\Support\DocumentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resolutions', function (Blueprint $table) {
            $table->string('document_type', 20)->default(DocumentType::RESOLUTION)->index()->after('resolution_title');
        });

        Resolution::query()
            ->whereNotNull('legacy_sp_id')
            ->update(['document_type' => DocumentType::forMigratedRecord()]);

        Resolution::query()
            ->whereNull('legacy_sp_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $resolution) {
                    $resolution->update([
                        'document_type' => DocumentType::infer(
                            $resolution->resolution_no,
                            $resolution->resolution_title,
                        ),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('resolutions', function (Blueprint $table) {
            $table->dropColumn('document_type');
        });
    }
};

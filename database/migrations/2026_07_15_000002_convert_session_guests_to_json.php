<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legislative_sessions', function (Blueprint $table) {
            $table->json('guests')->nullable()->after('notes');
        });

        $legacy = DB::table('legislative_sessions')
            ->select(['id', 'guest_name', 'guest_remarks'])
            ->where(function ($query): void {
                $query->whereNotNull('guest_name')->where('guest_name', '!=', '')
                    ->orWhere(function ($inner): void {
                        $inner->whereNotNull('guest_remarks')->where('guest_remarks', '!=', '');
                    });
            })
            ->get();

        foreach ($legacy as $session) {
            $name = trim((string) ($session->guest_name ?? ''));
            $remarks = trim((string) ($session->guest_remarks ?? ''));

            if ($name === '' && $remarks === '') {
                continue;
            }

            DB::table('legislative_sessions')
                ->where('id', $session->id)
                ->update([
                    'guests' => json_encode([
                        [
                            'name' => $name,
                            'remarks' => $remarks,
                        ],
                    ]),
                ]);
        }

        Schema::table('legislative_sessions', function (Blueprint $table) {
            $table->dropColumn(['guest_name', 'guest_remarks']);
        });
    }

    public function down(): void
    {
        Schema::table('legislative_sessions', function (Blueprint $table) {
            $table->string('guest_name')->nullable()->after('notes');
            $table->text('guest_remarks')->nullable()->after('guest_name');
        });

        $rows = DB::table('legislative_sessions')
            ->select(['id', 'guests'])
            ->whereNotNull('guests')
            ->get();

        foreach ($rows as $session) {
            $guests = json_decode((string) $session->guests, true);

            if (! is_array($guests) || $guests === []) {
                continue;
            }

            $first = $guests[0] ?? [];

            DB::table('legislative_sessions')
                ->where('id', $session->id)
                ->update([
                    'guest_name' => filled($first['name'] ?? null) ? (string) $first['name'] : null,
                    'guest_remarks' => filled($first['remarks'] ?? null) ? (string) $first['remarks'] : null,
                ]);
        }

        Schema::table('legislative_sessions', function (Blueprint $table) {
            $table->dropColumn('guests');
        });
    }
};

<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class MergedDocumentSql
{
    public static function resolutionSortNumberColumn(string $table = 'resolutions'): string
    {
        $column = $table.'.resolution_no';

        return match (DB::connection()->getDriverName()) {
            'mysql' => "CAST(COALESCE(REGEXP_SUBSTR({$column}, '[0-9]+'), '0') AS UNSIGNED)",
            default => 'CAST('.$table.'.id AS INTEGER)',
        };
    }
}

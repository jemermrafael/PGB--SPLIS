<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class SqlDateExpression
{
    public static function month(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "cast(strftime('%m', {$column}) as integer)",
            'mysql', 'mariadb' => "MONTH({$column})",
            default => "cast(extract(month from {$column}) as integer)",
        };
    }

    public static function year(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "cast(strftime('%Y', {$column}) as integer)",
            'mysql', 'mariadb' => "YEAR({$column})",
            default => "cast(extract(year from {$column}) as integer)",
        };
    }
}

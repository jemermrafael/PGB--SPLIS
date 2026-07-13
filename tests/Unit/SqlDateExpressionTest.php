<?php

namespace Tests\Unit;

use App\Support\SqlDateExpression;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SqlDateExpressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_month_expression_matches_driver(): void
    {
        $driver = DB::connection()->getDriverName();

        $expression = SqlDateExpression::month('date_received');

        if ($driver === 'sqlite') {
            $this->assertStringContainsString("strftime('%m'", $expression);
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->assertSame('MONTH(date_received)', $expression);
        } else {
            $this->assertStringContainsString('extract(month from date_received)', $expression);
        }
    }

    public function test_year_expression_matches_driver(): void
    {
        $driver = DB::connection()->getDriverName();

        $expression = SqlDateExpression::year('created_at');

        if ($driver === 'sqlite') {
            $this->assertStringContainsString("strftime('%Y'", $expression);
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->assertSame('YEAR(created_at)', $expression);
        } else {
            $this->assertStringContainsString('extract(year from created_at)', $expression);
        }
    }
}

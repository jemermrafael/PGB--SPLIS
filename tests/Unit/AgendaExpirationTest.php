<?php

namespace Tests\Unit;

use App\Models\AgendaItem;
use App\Support\AgendaDeadline;
use Carbon\Carbon;
use Tests\TestCase;

class AgendaExpirationTest extends TestCase
{
    public function test_expiring_soon_window_is_fourteen_days_by_default(): void
    {
        $this->assertSame(14, AgendaDeadline::expiringSoonDays());
    }

    public function test_is_within_expiring_soon_window_for_pending_item_due_in_fourteen_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-09'));

        $this->assertTrue(AgendaDeadline::isWithinExpiringSoonWindow(
            Carbon::parse('2026-07-23'),
            AgendaItem::STATUS_PENDING,
        ));
    }

    public function test_is_not_within_expiring_soon_window_when_due_later_than_fourteen_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-09'));

        $this->assertFalse(AgendaDeadline::isWithinExpiringSoonWindow(
            Carbon::parse('2026-07-24'),
            AgendaItem::STATUS_PENDING,
        ));
    }

    public function test_is_not_within_expiring_soon_window_for_completed_items(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-09'));

        $this->assertFalse(AgendaDeadline::isWithinExpiringSoonWindow(
            Carbon::parse('2026-07-15'),
            AgendaItem::STATUS_DONE,
        ));
    }
}

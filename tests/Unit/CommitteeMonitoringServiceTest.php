<?php

namespace Tests\Unit;

use App\Models\AgendaItem;
use App\Models\User;
use App\Services\CommitteeMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommitteeMonitoringServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_stats_and_filters_support_schedule_and_outcome_views(): void
    {
        $user = User::factory()->create();
        $service = app(CommitteeMonitoringService::class);

        AgendaItem::create([
            'title' => 'Pending item',
            'committee_referred' => 'Tourism',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);

        AgendaItem::create([
            'title' => 'Scheduled item',
            'committee_referred' => 'Tourism',
            'date_of_committee_meeting' => now()->addWeek(),
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);

        AgendaItem::create([
            'title' => 'Reported item',
            'committee_referred' => 'Tourism',
            'committee_report_url' => 'https://example.com/report.pdf',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);

        AgendaItem::create([
            'title' => 'Completed item',
            'committee_referred' => 'Tourism',
            'outcome' => 'Approved',
            'status' => AgendaItem::STATUS_DONE,
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);

        $baseFilters = [];
        $stats = $service->stats($baseFilters);

        $this->assertSame(4, $stats['total']);
        $this->assertSame(3, $stats['pending']);
        $this->assertSame(1, $stats['with_schedule']);
        $this->assertSame(1, $stats['with_report']);
        $this->assertSame(1, $stats['completed']);

        $this->assertSame(1, $service->paginate(['has_schedule' => 'yes'], 25)->total());
        $this->assertSame(1, $service->paginate(['has_report' => 'yes'], 25)->total());
        $this->assertSame(1, $service->paginate(['status' => 'completed'], 25)->total());
        $this->assertSame(3, $service->paginate(['status' => 'pending'], 25)->total());
    }
}

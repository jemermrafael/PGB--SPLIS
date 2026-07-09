<?php

namespace Tests\Unit;

use App\Models\AgendaItem;
use App\Support\ActivityLogger;
use Tests\TestCase;

class ActivityLoggerAgendaObTest extends TestCase
{
    public function test_agenda_ob_properties_includes_current_version_no(): void
    {
        $agenda = new AgendaItem(['current_version_no' => 3]);

        $properties = ActivityLogger::agendaObProperties($agenda, [
            'section' => 'unassigned_regular',
        ]);

        $this->assertSame(3, $properties['agenda_version_no']);
        $this->assertSame('unassigned_regular', $properties['section']);
    }
}

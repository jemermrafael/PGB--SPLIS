<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\Ordinance;
use App\Models\Resolution;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminActivityLogNotifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_agenda_created_notifies_active_admins(): void
    {
        $encoder = User::factory()->create(['role' => UserRole::Encoder]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $inactiveAdmin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => false]);

        $agenda = AgendaItem::create([
            'tracking_no' => 'TRK-001',
            'title' => 'Sample agenda',
            'sender' => 'Mariveles',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'created_by' => $encoder->id,
        ]);

        $this->actingAs($encoder);
        ActivityLogger::log('agenda.created', $agenda, [
            'tracking_no' => $agenda->tracking_no,
            'title' => $agenda->title,
            'sender' => $agenda->sender,
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $admin->id,
            'type' => UserNotification::TYPE_ACTIVITY_LOG,
            'title' => 'Agenda created',
        ]);

        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $inactiveAdmin->id,
            'type' => UserNotification::TYPE_ACTIVITY_LOG,
            'title' => 'Agenda created',
        ]);
    }

    public function test_agenda_published_notifies_active_admins(): void
    {
        $encoder = User::factory()->create(['role' => UserRole::Encoder]);
        $admin = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);

        $agenda = AgendaItem::create([
            'tracking_no' => 'TRK-002',
            'title' => 'Published agenda',
            'status' => AgendaItem::STATUS_DONE,
            'prescribed_days' => 0,
            'reso_ord_ao_no' => '2026-15',
            'reso_ord_ao_series' => 2026,
            'created_by' => $encoder->id,
        ]);

        $this->actingAs($encoder);
        ActivityLogger::log('agenda.published', $agenda, [
            'target' => 'Resolution',
            'output_no' => $agenda->reso_ord_ao_no,
            'tracking_no' => $agenda->tracking_no,
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $admin->id,
            'type' => UserNotification::TYPE_ACTIVITY_LOG,
            'title' => 'Agenda published',
        ]);
    }

    public function test_resolution_and_ordinance_created_notifies_active_admins(): void
    {
        $encoder = User::factory()->create(['role' => UserRole::Encoder]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $resolution = Resolution::create([
            'resolution_no' => '15',
            'resolution_title' => 'Direct resolution',
            'series' => 2026,
            'status' => 'draft',
            'created_by' => $encoder->id,
        ]);

        $ordinance = Ordinance::create([
            'ordinance_no' => 42,
            'series_year' => 2026,
            'subject' => 'Direct ordinance',
        ]);

        $this->actingAs($encoder);
        ActivityLogger::log('resolution.created', $resolution, ['resolution_no' => $resolution->resolution_no]);
        ActivityLogger::log('ordinance.created', $ordinance, [
            'ordinance_no' => $ordinance->ordinance_no,
            'series_year' => $ordinance->series_year,
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $admin->id,
            'title' => 'Resolution created',
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $admin->id,
            'title' => 'Ordinance created',
        ]);
    }
}

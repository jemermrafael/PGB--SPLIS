<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\Municipality;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\MunicipalNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MunicipalNotifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_notify_expiring_soon_targets_matching_municipal_users(): void
    {
        $municipality = Municipality::query()->create([
            'code' => 201,
            'description' => 'Orani',
        ]);

        $user = User::factory()->create([
            'role' => UserRole::MunicipalViewer,
            'username' => 'orani_viewer',
            'is_active' => true,
            'municipality_id' => $municipality->id,
        ]);

        User::factory()->create([
            'role' => UserRole::MunicipalViewer,
            'username' => 'other_viewer',
            'is_active' => true,
            'municipality_id' => Municipality::query()->create([
                'code' => 202,
                'description' => 'Balanga',
            ])->id,
        ]);

        $agenda = AgendaItem::query()->create([
            'title' => 'Municipal request',
            'sender' => 'Orani',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 14,
            'date_received' => now()->subDays(5)->toDateString(),
            'due_date' => now()->addDays(9)->toDateString(),
            'created_by' => $user->id,
        ]);

        app(MunicipalNotifier::class)->notifyAgendaExpiringSoon($agenda);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $user->id,
            'agenda_item_id' => $agenda->id,
            'type' => UserNotification::TYPE_AGENDA_EXPIRING_SOON,
        ]);

        $this->assertSame(1, UserNotification::query()
            ->where('agenda_item_id', $agenda->id)
            ->where('type', UserNotification::TYPE_AGENDA_EXPIRING_SOON)
            ->count());
    }
}

<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_board_member_notifications_page_returns_html(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::BoardMember,
            'username' => 'bm_notify',
            'is_active' => true,
        ]);

        UserNotification::query()->create([
            'user_id' => $user->id,
            'type' => UserNotification::TYPE_AGENDA_EXPIRING_SOON,
            'title' => 'Agenda deadline approaching',
            'body' => '#290 is due soon.',
            'link' => '/agenda/291',
        ]);

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Notifications')
            ->assertSee('Agenda deadline approaching')
            ->assertDontSee('"has_more"');
    }

    public function test_notifications_feed_endpoint_returns_json(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::BoardMember,
            'username' => 'bm_notify_feed',
            'is_active' => true,
        ]);

        UserNotification::query()->create([
            'user_id' => $user->id,
            'type' => UserNotification::TYPE_AGENDA_EXPIRING_SOON,
            'title' => 'Agenda deadline approaching',
            'body' => '#290 is due soon.',
            'link' => '/agenda/291',
        ]);

        $this->actingAs($user)
            ->getJson(route('notifications.feed'))
            ->assertOk()
            ->assertJsonPath('notifications.0.title', 'Agenda deadline approaching')
            ->assertJsonStructure(['notifications', 'has_more', 'next_before_id', 'count']);
    }

    public function test_legacy_notifications_all_redirects_to_index(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::BoardMember,
            'username' => 'bm_notify_redir',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/notifications/all')
            ->assertRedirect('/notifications');
    }

    public function test_municipal_viewer_can_open_notifications_page(): void
    {
        $municipality = \App\Models\Municipality::query()->create([
            'code' => 101,
            'description' => 'Mariveles',
        ]);

        $user = User::factory()->create([
            'role' => UserRole::MunicipalViewer,
            'username' => 'muni_notify',
            'is_active' => true,
            'municipality_id' => $municipality->id,
        ]);

        UserNotification::query()->create([
            'user_id' => $user->id,
            'type' => UserNotification::TYPE_AGENDA_EXPIRING_SOON,
            'title' => 'Request deadline approaching',
            'body' => '#10 is due soon.',
            'link' => '/my-requests/10',
        ]);

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Notifications')
            ->assertSee('Request deadline approaching');
    }
}

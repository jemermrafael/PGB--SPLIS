<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\User;
use App\Services\AgendaItemRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendaArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_archive_agenda_and_it_disappears_from_agenda_list(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $agenda = $this->makeAgenda($admin, 'Archive me from list');

        $this->actingAs($admin)
            ->post(route('agenda.archive', $agenda))
            ->assertRedirect(route('admin.archives.index'));

        $agenda->refresh();
        $this->assertTrue($agenda->isArchived());
        $this->assertSame($admin->id, $agenda->archived_by);

        $page = app(AgendaItemRepository::class)->paginate();
        $ids = collect($page->items())->pluck('id');
        $this->assertFalse($ids->contains($agenda->id));

        $this->actingAs($admin)
            ->get(route('agenda.search'))
            ->assertOk()
            ->assertJsonMissing(['title' => 'Archive me from list']);
    }

    public function test_archived_agenda_appears_in_archives_and_can_be_restored(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);
        $agenda = $this->makeAgenda($admin, 'Restorable archived agenda');
        $agenda->archive($admin);

        $this->actingAs($admin)
            ->get(route('admin.archives.index'))
            ->assertOk()
            ->assertSee('Archives')
            ->assertSee('id="archives-search"', false);

        $this->actingAs($admin)
            ->getJson(route('admin.archives.search', ['title' => 'Restorable']))
            ->assertOk()
            ->assertJsonFragment(['title' => 'Restorable archived agenda']);

        $this->actingAs($admin)
            ->post(route('agenda.restore-archive', $agenda))
            ->assertRedirect(route('agenda.show', $agenda));

        $agenda->refresh();
        $this->assertFalse($agenda->isArchived());
        $this->assertNull($agenda->archived_by);

        $page = app(AgendaItemRepository::class)->paginate();
        $ids = collect($page->items())->pluck('id');
        $this->assertTrue($ids->contains($agenda->id));
    }

    public function test_encoder_cannot_archive_or_open_archives(): void
    {
        $encoder = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);
        $agenda = $this->makeAgenda($encoder, 'Encoder cannot archive');

        $this->actingAs($encoder)
            ->post(route('agenda.archive', $agenda))
            ->assertForbidden();

        $this->actingAs($encoder)
            ->get(route('admin.archives.index'))
            ->assertForbidden();
    }

    public function test_account_menu_archives_link_visible_for_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Archives')
            ->assertSee(route('admin.archives.index'), false);
    }

    protected function makeAgenda(User $user, string $title): AgendaItem
    {
        return AgendaItem::query()->create([
            'tracking_no' => (string) fake()->unique()->numberBetween(1000, 9999),
            'title' => $title,
            'status' => AgendaItem::STATUS_PENDING,
            'date_received' => now()->toDateString(),
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);
    }
}

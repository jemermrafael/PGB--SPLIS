<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\PageBackground;
use App\Models\User;
use App\Support\PageBackgrounds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PageBackgroundTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        PageBackgrounds::flushCache();
        parent::tearDown();
    }

    protected function settingsAdmin(): User
    {
        return User::factory()->create([
            'name' => 'Jemer M. Rafael',
            'role' => UserRole::Superadmin,
            'is_active' => true,
        ]);
    }

    public function test_named_superadmin_can_open_pages_settings(): void
    {
        $this->actingAs($this->settingsAdmin())
            ->get(route('admin.pages.index'))
            ->assertOk()
            ->assertSee('Pages', false)
            ->assertSee('Background Type', false)
            ->assertSee('Directory', false);
    }

    public function test_other_superadmin_cannot_open_pages_settings(): void
    {
        $user = User::factory()->create([
            'name' => 'Other Admin',
            'role' => UserRole::Superadmin,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('admin.pages.index'))
            ->assertForbidden();
    }

    public function test_named_superadmin_can_save_classic_background_with_image(): void
    {
        Storage::fake('local');

        $user = $this->settingsAdmin();

        $this->actingAs($user)
            ->put(route('admin.pages.update', 'directory'), [
                'background_type' => 'classic',
                'color' => '#e2e8f0',
                'image' => UploadedFile::fake()->image('office.jpg', 800, 600),
                'position' => 'center center',
                'attachment' => 'scroll',
                'repeat' => 'no-repeat',
                'size' => 'cover',
            ])
            ->assertRedirect(route('admin.pages.index'));

        $background = PageBackground::query()->where('page_key', 'directory')->first();
        $this->assertNotNull($background);
        $this->assertSame('classic', $background->background_type);
        $this->assertSame('#e2e8f0', $background->color);
        $this->assertSame('cover', $background->size);
        $this->assertTrue($background->hasImage());

        $this->actingAs($user)
            ->get(route('directory.index'))
            ->assertOk()
            ->assertSee('splis-main--custom-bg', false)
            ->assertSee(route('page-backgrounds.show', $background), false);
    }

    public function test_clearing_background_type_removes_settings(): void
    {
        Storage::fake('local');

        $user = $this->settingsAdmin();

        $this->actingAs($user)
            ->put(route('admin.pages.update', 'directory'), [
                'background_type' => 'classic',
                'color' => '#ffffff',
                'image' => UploadedFile::fake()->image('bg.png', 200, 200),
                'position' => 'default',
                'attachment' => 'default',
                'repeat' => 'default',
                'size' => 'default',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->put(route('admin.pages.update', 'directory'), [
                'background_type' => 'none',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('page_backgrounds', ['page_key' => 'directory']);
    }

    public function test_user_menu_shows_settings_group_for_named_superadmin(): void
    {
        $this->actingAs($this->settingsAdmin())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Settings', false)
            ->assertSee('Icon Library', false)
            ->assertSee(route('admin.pages.index'), false);
    }

    public function test_pages_settings_lists_each_committee(): void
    {
        \App\Models\Committee::query()->create([
            'name' => 'Committee on Agriculture',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        \App\Models\Committee::query()->create([
            'name' => 'Committee on Education',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $html = $this->actingAs($this->settingsAdmin())
            ->get(route('admin.pages.index'))
            ->assertOk()
            ->assertSee('Dashboard', false)
            ->assertSee('Committee items', false)
            ->assertSee('Committee on Agriculture', false)
            ->assertSee('Committee on Education', false)
            ->getContent();

        $this->assertStringContainsString('admin/pages/committee_', $html);
    }

    public function test_each_committee_show_page_uses_its_own_background(): void
    {
        Storage::fake('local');

        $user = $this->settingsAdmin();
        $term = \App\Models\CommitteeTerm::currentOrCreate();

        $alpha = \App\Models\Committee::query()->create([
            'name' => 'Committee Alpha',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $beta = \App\Models\Committee::query()->create([
            'name' => 'Committee Beta',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $this->actingAs($user)
            ->put(route('admin.pages.update', PageBackgrounds::committeePageKey($alpha->id)), [
                'background_type' => 'classic',
                'color' => '#ffeeee',
                'image' => UploadedFile::fake()->image('alpha.jpg', 400, 300),
                'position' => 'center center',
                'attachment' => 'scroll',
                'repeat' => 'no-repeat',
                'size' => 'cover',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->put(route('admin.pages.update', PageBackgrounds::committeePageKey($beta->id)), [
                'background_type' => 'classic',
                'color' => '#eeffee',
                'position' => 'top left',
                'attachment' => 'fixed',
                'repeat' => 'repeat',
                'size' => 'contain',
            ])
            ->assertRedirect();

        $alphaBg = PageBackground::query()
            ->where('page_key', PageBackgrounds::committeePageKey($alpha->id))
            ->first();
        $betaBg = PageBackground::query()
            ->where('page_key', PageBackgrounds::committeePageKey($beta->id))
            ->first();

        $this->assertNotNull($alphaBg);
        $this->assertNotNull($betaBg);
        $this->assertTrue($alphaBg->hasImage());
        $this->assertFalse($betaBg->hasImage());

        $this->actingAs($user)
            ->get(route('committees.show', ['committee' => $alpha, 'term' => $term->id]))
            ->assertOk()
            ->assertSee('splis-main--custom-bg', false)
            ->assertSee(route('page-backgrounds.show', $alphaBg), false)
            ->assertDontSee(route('page-backgrounds.show', $betaBg), false);

        $this->actingAs($user)
            ->get(route('committees.show', ['committee' => $beta, 'term' => $term->id]))
            ->assertOk()
            ->assertSee('splis-main--custom-bg', false)
            ->assertSee('#eeffee', false)
            ->assertDontSee(route('page-backgrounds.show', $alphaBg), false);
    }

    public function test_dashboard_and_committee_list_resolve_their_page_keys(): void
    {
        $this->assertSame('dashboard', PageBackgrounds::resolvePageKey('dashboard'));
        $this->assertSame('dashboard', PageBackgrounds::resolvePageKey('dashboard.documents.search'));
        $this->assertSame('committees', PageBackgrounds::resolvePageKey('committees.index'));
        $this->assertSame('committees', PageBackgrounds::resolvePageKey('committees.create'));
    }
}

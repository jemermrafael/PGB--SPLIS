<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Committee;
use App\Models\CommitteeTerm;
use App\Models\IconLibraryItem;
use App\Models\PageIconOverride;
use App\Models\User;
use App\Support\CommitteeIcon;
use App\Support\IconLibrary;
use App\Support\PageIcon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IconLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CommitteeIcon::flushLookupCache();
        PageIcon::flushCache();
        parent::tearDown();
    }

    protected function iconLibrarian(): User
    {
        return User::factory()->create([
            'name' => 'Jemer M. Rafael',
            'role' => UserRole::Superadmin,
            'is_active' => true,
        ]);
    }

    public function test_named_superadmin_can_open_icon_library_page(): void
    {
        $this->actingAs($this->iconLibrarian())
            ->get(route('admin.icons.index'))
            ->assertOk()
            ->assertSee('Icon Library', false)
            ->assertSee('Page title icons', false)
            ->assertSee('Built-in presets', false);
    }

    public function test_other_superadmin_cannot_open_icon_library_page(): void
    {
        $superadmin = User::factory()->create([
            'name' => 'Other Admin',
            'role' => UserRole::Superadmin,
            'is_active' => true,
        ]);

        $this->actingAs($superadmin)
            ->get(route('admin.icons.index'))
            ->assertForbidden();
    }

    public function test_admin_cannot_open_icon_library_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.icons.index'))
            ->assertForbidden();
    }

    public function test_named_superadmin_can_upload_and_delete_library_icon(): void
    {
        Storage::fake('local');

        $user = $this->iconLibrarian();

        $this->actingAs($user)
            ->post(route('admin.icons.store'), [
                'icons' => [
                    UploadedFile::fake()->image('landmark.png', 48, 48),
                ],
            ])
            ->assertRedirect();

        $item = IconLibraryItem::query()->first();
        $this->assertNotNull($item);
        $this->assertSame('landmark', $item->name);
        $this->assertTrue(Storage::disk('local')->exists($item->stored_path));

        $this->actingAs($user)
            ->get(route('icon-library.show', $item))
            ->assertOk();

        $this->actingAs($user)
            ->delete(route('admin.icons.destroy', $item))
            ->assertRedirect();

        $this->assertDatabaseMissing('icon_library_items', ['id' => $item->id]);
        Storage::disk('local')->assertMissing($item->stored_path);
    }

    public function test_named_superadmin_can_upload_multiple_library_icons(): void
    {
        Storage::fake('local');

        $this->actingAs($this->iconLibrarian())
            ->post(route('admin.icons.store'), [
                'icons' => [
                    UploadedFile::fake()->image('alpha.png', 32, 32),
                    UploadedFile::fake()->image('beta.png', 32, 32),
                    UploadedFile::fake()->image('gamma.png', 32, 32),
                ],
            ])
            ->assertRedirect();

        $this->assertSame(3, IconLibraryItem::query()->count());
        $this->assertTrue(IconLibraryItem::query()->where('name', 'alpha')->exists());
        $this->assertTrue(IconLibraryItem::query()->where('name', 'beta')->exists());
        $this->assertTrue(IconLibraryItem::query()->where('name', 'gamma')->exists());
    }

    public function test_named_superadmin_can_assign_library_icon_to_committee(): void
    {
        Storage::fake('local');

        $user = $this->iconLibrarian();
        $term = CommitteeTerm::currentOrCreate();
        $committee = Committee::create([
            'sort_order' => 1,
            'name' => 'Committee on Tourism',
            'is_active' => true,
            'icon_key' => 'map',
        ]);

        $item = IconLibrary::store(
            UploadedFile::fake()->image('tourism.png', 40, 40),
            'Tourism mark',
            $user->id,
        );

        $this->actingAs($user)
            ->put(route('committees.update', $committee), [
                'sort_order' => 1,
                'name' => $committee->name,
                'is_active' => '1',
                'committee_term_id' => $term->id,
                'icon_key' => 'map',
                'icon_library_id' => $item->id,
            ])
            ->assertRedirect();

        $committee->refresh();

        $this->assertSame($item->id, $committee->icon_library_id);
        $this->assertNull($committee->icon_path);
        $this->assertTrue(CommitteeIcon::hasCustomFile($committee));
        $this->assertSame(route('icon-library.show', $item), CommitteeIcon::customUrl($committee));
    }

    public function test_named_superadmin_can_assign_page_title_icon(): void
    {
        Storage::fake('local');

        $user = $this->iconLibrarian();
        $item = IconLibrary::store(
            UploadedFile::fake()->image('resolutions.png', 40, 40),
            'resolutions',
            $user->id,
        );

        $this->actingAs($user)
            ->put(route('admin.icons.pages'), [
                'pages' => [
                    'resolutions' => $item->id,
                    'ordinances' => '',
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('page_icon_overrides', [
            'page_key' => 'resolutions',
            'icon_library_id' => $item->id,
        ]);

        $this->assertSame(route('icon-library.show', $item), PageIcon::customUrl('resolutions'));
        $this->assertNull(PageIcon::customUrl('ordinances'));

        $this->actingAs($user)
            ->get(route('resolutions.index'))
            ->assertOk()
            ->assertSee(route('icon-library.show', $item), false);
    }

    public function test_named_superadmin_can_clear_page_title_icon(): void
    {
        Storage::fake('local');

        $user = $this->iconLibrarian();
        $item = IconLibrary::store(
            UploadedFile::fake()->image('agenda.png', 40, 40),
            'agenda',
            $user->id,
        );

        PageIconOverride::query()->create([
            'page_key' => 'agenda',
            'icon_library_id' => $item->id,
        ]);

        $this->actingAs($user)
            ->put(route('admin.icons.pages'), [
                'pages' => [
                    'agenda' => '',
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('page_icon_overrides', ['page_key' => 'agenda']);
        $this->assertNull(PageIcon::customUrl('agenda'));
    }
}

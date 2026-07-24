<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Committee;
use App\Models\CommitteeTerm;
use App\Models\IconLibraryItem;
use App\Models\User;
use App\Support\CommitteeIcon;
use App\Support\IconLibrary;
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
        parent::tearDown();
    }

    public function test_superadmin_can_open_icon_library_page(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);

        $this->actingAs($superadmin)
            ->get(route('admin.icons.index'))
            ->assertOk()
            ->assertSee('Icon Library', false)
            ->assertSee('Built-in presets', false);
    }

    public function test_admin_cannot_open_icon_library_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.icons.index'))
            ->assertForbidden();
    }

    public function test_superadmin_can_upload_and_delete_library_icon(): void
    {
        Storage::fake('local');

        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);

        $this->actingAs($superadmin)
            ->post(route('admin.icons.store'), [
                'name' => 'Landmark',
                'icon' => UploadedFile::fake()->image('landmark.png', 48, 48),
            ])
            ->assertRedirect();

        $item = IconLibraryItem::query()->first();
        $this->assertNotNull($item);
        $this->assertSame('Landmark', $item->name);
        $this->assertTrue(Storage::disk('local')->exists($item->stored_path));

        $this->actingAs($superadmin)
            ->get(route('icon-library.show', $item))
            ->assertOk();

        $this->actingAs($superadmin)
            ->delete(route('admin.icons.destroy', $item))
            ->assertRedirect();

        $this->assertDatabaseMissing('icon_library_items', ['id' => $item->id]);
        Storage::disk('local')->assertMissing($item->stored_path);
    }

    public function test_superadmin_can_assign_library_icon_to_committee(): void
    {
        Storage::fake('local');

        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);
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
            $superadmin->id,
        );

        $this->actingAs($superadmin)
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
}

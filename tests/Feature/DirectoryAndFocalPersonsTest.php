<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\DirectoryCategory;
use App\Models\DirectoryEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectoryAndFocalPersonsTest extends TestCase
{
    use RefreshDatabase;

    public function test_encoder_can_create_directory_entry_with_multiple_emails_and_category(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);
        $category = DirectoryCategory::query()->create([
            'name' => 'SP Secretariat',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->post(route('directory.store'), [
                'name' => 'Records Office',
                'directory_category_id' => $category->id,
                'contact_number' => '09171234567',
                'emails' => [
                    'records@bataan.gov.ph',
                    'records2@bataan.gov.ph',
                    'archive@bataan.gov.ph',
                ],
                'designation' => 'Office',
                'sort_order' => 1,
            ])
            ->assertRedirect(route('directory.index'));

        $entry = DirectoryEntry::query()->first();
        $this->assertNotNull($entry);
        $this->assertSame($category->id, $entry->directory_category_id);
        $this->assertSame([
            'records@bataan.gov.ph',
            'records2@bataan.gov.ph',
            'archive@bataan.gov.ph',
        ], $entry->emailList());
        $this->assertSame('records@bataan.gov.ph', $entry->email);
    }

    public function test_directory_index_and_create_hide_category_and_focal_persons(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);
        DirectoryCategory::query()->create([
            'name' => 'Provincial Offices',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('directory.index'))
            ->assertOk()
            ->assertDontSee('Manage Categories')
            ->assertDontSee('Provincial Offices')
            ->assertDontSee('>Category</th>', false)
            ->assertDontSee('>Focal persons</th>', false);

        $this->actingAs($user)
            ->get(route('directory.create'))
            ->assertOk()
            ->assertDontSee('directory_category_id')
            ->assertDontSee('Focal persons')
            ->assertDontSee('Manage Categories');
    }

    public function test_create_form_defaults_sort_order_to_next_after_last_entry(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);

        DirectoryEntry::query()->create([
            'name' => 'First Entry',
            'sort_order' => 7,
        ]);

        $this->actingAs($user)
            ->get(route('directory.create'))
            ->assertOk()
            ->assertSee('value="8"', false);
    }

    public function test_encoder_can_manage_directory_categories(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);

        $this->actingAs($user)
            ->post(route('directory.categories.store'), [
                'name' => 'Provincial Offices',
                'sort_order' => 2,
            ])
            ->assertRedirect();

        $category = DirectoryCategory::query()->first();
        $this->assertNotNull($category);
        $this->assertSame('Provincial Offices', $category->name);

        $this->actingAs($user)
            ->put(route('directory.categories.update', $category), [
                'name' => 'PGO Offices',
                'sort_order' => 3,
            ])
            ->assertRedirect();

        $this->assertSame('PGO Offices', $category->fresh()->name);
    }

    public function test_encoder_can_save_provincial_board_focal_persons_on_directory_entry(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);
        $category = DirectoryCategory::query()->create([
            'name' => DirectoryCategory::PROVINCIAL_BOARD,
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->post(route('directory.store'), [
                'name' => 'Hon. Juan Dela Cruz',
                'directory_category_id' => $category->id,
                'emails' => ['juan@bataan.gov.ph'],
                'focal_persons' => [
                    [
                        'name' => 'Ana Santos',
                        'emails' => ['ana@example.com', 'ana.office@example.com'],
                    ],
                    [
                        'name' => 'Ben Reyes',
                        'emails' => ['ben@example.com'],
                    ],
                ],
                'designation' => 'Board Member',
                'sort_order' => 1,
            ])
            ->assertRedirect(route('directory.index'));

        $entry = DirectoryEntry::query()->first();
        $this->assertNotNull($entry);
        $this->assertSame([
            [
                'name' => 'Ana Santos',
                'emails' => ['ana@example.com', 'ana.office@example.com'],
            ],
            [
                'name' => 'Ben Reyes',
                'emails' => ['ben@example.com'],
            ],
        ], $entry->focalPersonsList());
    }

    public function test_focal_persons_are_cleared_when_category_is_not_provincial_board(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);
        $provincial = DirectoryCategory::query()->create([
            'name' => DirectoryCategory::PROVINCIAL_BOARD,
            'sort_order' => 1,
        ]);
        $other = DirectoryCategory::query()->create([
            'name' => 'SP Secretariat',
            'sort_order' => 2,
        ]);

        $entry = DirectoryEntry::query()->create([
            'name' => 'Hon. Juan Dela Cruz',
            'directory_category_id' => $provincial->id,
            'email' => 'juan@bataan.gov.ph',
            'emails' => ['juan@bataan.gov.ph'],
            'focal_persons' => [
                ['name' => 'Ana Santos', 'emails' => ['ana@example.com']],
            ],
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->put(route('directory.update', $entry), [
                'name' => 'Hon. Juan Dela Cruz',
                'directory_category_id' => $other->id,
                'emails' => ['juan@bataan.gov.ph'],
                'focal_persons' => [
                    ['name' => 'Ana Santos', 'emails' => ['ana@example.com']],
                ],
                'sort_order' => 1,
            ])
            ->assertRedirect(route('directory.index'));

        $this->assertNull($entry->fresh()->focal_persons);
    }

    public function test_directory_search_returns_matching_entries_as_json(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);
        $category = DirectoryCategory::query()->create([
            'name' => 'SP Secretariat',
            'sort_order' => 1,
        ]);

        DirectoryEntry::query()->create([
            'name' => 'Records Office',
            'directory_category_id' => $category->id,
            'emails' => ['records@bataan.gov.ph'],
            'sort_order' => 1,
        ]);
        DirectoryEntry::query()->create([
            'name' => 'Unrelated Office',
            'sort_order' => 2,
        ]);

        $this->actingAs($user)
            ->getJson(route('directory.search', ['q' => 'Records']))
            ->assertOk()
            ->assertJsonStructure(['html', 'meta' => ['total', 'current_page', 'last_page']])
            ->assertJsonPath('meta.total', 1)
            ->assertSee('Records Office', false);
    }

    public function test_encoder_can_bulk_delete_directory_entries(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);

        $first = DirectoryEntry::query()->create([
            'name' => 'First Office',
            'sort_order' => 1,
        ]);
        $second = DirectoryEntry::query()->create([
            'name' => 'Second Office',
            'sort_order' => 2,
        ]);
        $kept = DirectoryEntry::query()->create([
            'name' => 'Kept Office',
            'sort_order' => 3,
        ]);

        $this->actingAs($user)
            ->delete(route('directory.bulk-destroy'), [
                'ids' => [$first->id, $second->id],
            ])
            ->assertRedirect(route('directory.index'));

        $this->assertSoftDeleted($first);
        $this->assertSoftDeleted($second);
        $this->assertDatabaseHas('directory_entries', [
            'id' => $kept->id,
            'deleted_at' => null,
        ]);
    }

    public function test_directory_index_shows_bulk_select_controls_for_encoders(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);

        DirectoryEntry::query()->create([
            'name' => 'Records Office',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('directory.index'))
            ->assertOk()
            ->assertSee('data-directory-select-all', false)
            ->assertSee('data-directory-checkbox', false)
            ->assertSee('data-directory-bulk-form', false)
            ->assertSee('Edit List', false);
    }

    public function test_encoder_can_reorder_directory_entries_with_move(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);

        $first = DirectoryEntry::query()->create([
            'name' => 'Alpha Office',
            'sort_order' => 1,
        ]);
        $second = DirectoryEntry::query()->create([
            'name' => 'Beta Office',
            'sort_order' => 2,
        ]);
        $third = DirectoryEntry::query()->create([
            'name' => 'Gamma Office',
            'sort_order' => 3,
        ]);

        $this->actingAs($user)
            ->post(route('directory.move', $second), [
                'direction' => -1,
            ])
            ->assertRedirect(route('directory.index'));

        $this->assertSame(1, $second->fresh()->sort_order);
        $this->assertSame(2, $first->fresh()->sort_order);
        $this->assertSame(3, $third->fresh()->sort_order);

        $this->actingAs($user)
            ->post(route('directory.move', $second), [
                'direction' => 1,
                'page' => 1,
            ])
            ->assertRedirect(route('directory.index', ['page' => 1]));

        $this->assertSame(1, $first->fresh()->sort_order);
        $this->assertSame(2, $second->fresh()->sort_order);
        $this->assertSame(3, $third->fresh()->sort_order);
    }
}

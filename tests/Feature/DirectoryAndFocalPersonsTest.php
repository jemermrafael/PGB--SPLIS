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

    public function test_create_form_remembers_last_picked_category(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);
        $category = DirectoryCategory::query()->create([
            'name' => 'Provincial Offices',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->post(route('directory.store'), [
                'name' => 'PGO',
                'directory_category_id' => $category->id,
                'emails' => ['pgo@bataan.gov.ph'],
                'sort_order' => 1,
            ])
            ->assertRedirect(route('directory.index'));

        $this->actingAs($user)
            ->get(route('directory.create'))
            ->assertOk()
            ->assertSee('value="'.$category->id.'" selected', false);
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
}

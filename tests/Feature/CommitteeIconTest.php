<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Committee;
use App\Models\CommitteeTerm;
use App\Models\User;
use App\Support\CommitteeIcon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommitteeIconTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CommitteeIcon::flushLookupCache();
        parent::tearDown();
    }

    public function test_superadmin_can_set_preset_icon_key(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);
        $term = CommitteeTerm::currentOrCreate();
        $committee = Committee::create([
            'sort_order' => 1,
            'name' => 'Committee on Special Projects',
            'is_active' => true,
        ]);

        $this->actingAs($superadmin)
            ->put(route('committees.update', $committee), $this->payload($term, $committee, [
                'icon_key' => 'trophy',
            ]))
            ->assertRedirect(route('committees.show', ['committee' => $committee, 'term' => $term->id]));

        $committee->refresh();
        $this->assertSame('trophy', $committee->icon_key);
        $this->assertNull($committee->icon_path);
        $this->assertSame('trophy', CommitteeIcon::resolveKey($committee));
    }

    public function test_admin_cannot_change_committee_icon(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $term = CommitteeTerm::currentOrCreate();
        $committee = Committee::create([
            'sort_order' => 1,
            'name' => 'Committee on Special Projects',
            'is_active' => true,
            'icon_key' => 'building',
        ]);

        $this->actingAs($admin)
            ->put(route('committees.update', $committee), $this->payload($term, $committee, [
                'icon_key' => 'trophy',
            ]))
            ->assertRedirect();

        $committee->refresh();
        $this->assertSame('building', $committee->icon_key);
    }

    public function test_uploaded_icon_overrides_preset_key(): void
    {
        Storage::fake('local');

        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);
        $term = CommitteeTerm::currentOrCreate();
        $committee = Committee::create([
            'sort_order' => 2,
            'name' => 'Committee on Health',
            'is_active' => true,
            'icon_key' => 'heart',
        ]);

        $file = UploadedFile::fake()->image('health.png', 64, 64);

        $this->actingAs($superadmin)
            ->put(route('committees.update', $committee), $this->payload($term, $committee, [
                'icon_key' => 'heart',
                'icon' => $file,
            ]))
            ->assertRedirect();

        $committee->refresh();
        $this->assertSame('heart', $committee->icon_key);
        $this->assertNotNull($committee->icon_path);
        $this->assertTrue(Storage::disk('local')->exists($committee->icon_path));
        $this->assertTrue(CommitteeIcon::hasCustomFile($committee));
        $this->assertSame(route('committees.icon', $committee), CommitteeIcon::customUrl($committee));
    }

    public function test_removing_upload_falls_back_to_preset_or_auto(): void
    {
        Storage::fake('local');

        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);
        $term = CommitteeTerm::currentOrCreate();
        $committee = Committee::create([
            'sort_order' => 3,
            'name' => 'Committee on Agriculture',
            'is_active' => true,
            'icon_key' => 'leaf',
        ]);

        CommitteeIcon::storeUpload($committee, UploadedFile::fake()->image('leaf.png', 32, 32));
        $committee->refresh();
        $path = $committee->icon_path;
        $this->assertTrue(Storage::disk('local')->exists($path));

        $this->actingAs($superadmin)
            ->put(route('committees.update', $committee), $this->payload($term, $committee, [
                'icon_key' => 'leaf',
                'remove_icon' => '1',
            ]))
            ->assertRedirect();

        $committee->refresh();
        $this->assertNull($committee->icon_path);
        $this->assertFalse(Storage::disk('local')->exists($path));
        $this->assertSame('leaf', CommitteeIcon::resolveKey($committee));
        $this->assertNull(CommitteeIcon::customUrl($committee));
    }

    public function test_authenticated_user_can_fetch_custom_icon_and_guest_cannot(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $committee = Committee::create([
            'sort_order' => 4,
            'name' => 'Committee on Tourism',
            'is_active' => true,
        ]);

        CommitteeIcon::storeUpload($committee, UploadedFile::fake()->image('tourism.png', 40, 40));
        $committee->refresh();

        $this->get(route('committees.icon', $committee))
            ->assertRedirect();

        $this->actingAs($admin)
            ->get(route('committees.icon', $committee))
            ->assertOk();
    }

    public function test_auto_icon_key_clears_preset(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);
        $term = CommitteeTerm::currentOrCreate();
        $committee = Committee::create([
            'sort_order' => 5,
            'name' => 'Committee on Justice',
            'is_active' => true,
            'icon_key' => 'building',
        ]);

        $this->actingAs($superadmin)
            ->put(route('committees.update', $committee), $this->payload($term, $committee, [
                'icon_key' => '',
            ]))
            ->assertRedirect();

        $committee->refresh();
        $this->assertNull($committee->icon_key);
        $this->assertSame('scales', CommitteeIcon::resolveKey($committee));
    }

    public function test_list_icon_fields_use_committee_override(): void
    {
        Storage::fake('local');

        $committee = Committee::create([
            'sort_order' => 6,
            'name' => 'Committee on Education',
            'is_active' => true,
            'icon_key' => 'trophy',
        ]);

        CommitteeIcon::storeUpload($committee, UploadedFile::fake()->image('edu.png', 24, 24));
        $committee->refresh();
        CommitteeIcon::flushLookupCache();

        $fields = CommitteeIcon::listIconFields('Committee on Education');

        $this->assertSame('trophy', $fields['committee_icon_key']);
        $this->assertSame(route('committees.icon', $committee), $fields['committee_icon_url']);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function payload(CommitteeTerm $term, Committee $committee, array $extra = []): array
    {
        return array_merge([
            'sort_order' => $committee->sort_order,
            'name' => $committee->name,
            'email' => $committee->email,
            'is_active' => '1',
            'committee_term_id' => $term->id,
            'chair_id' => '',
            'vice_chair_id' => '',
            'secretary' => '',
            'member_ids' => [],
        ], $extra);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\LegislativeSession;
use App\Models\ObDocument;
use App\Models\Resolution;
use App\Models\User;
use App\Support\IncomingFieldOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class EnhancementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_encoder_can_soft_delete_resolution(): void
    {
        $encoder = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);

        $resolution = Resolution::create([
            'resolution_no' => '7',
            'resolution_title' => 'Encoder trash test',
            'series' => 2026,
            'status' => 'draft',
            'created_by' => $encoder->id,
        ]);

        $this->actingAs($encoder)
            ->delete(route('resolutions.destroy', $resolution))
            ->assertRedirect(route('resolutions.index'));

        $this->assertTrue($resolution->fresh()->trashed());
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'resolution.trashed',
            'subject_id' => $resolution->id,
        ]);
    }

    public function test_keywords_endpoint_uses_cache_and_invalidates_on_update(): void
    {
        $user = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);

        Resolution::create([
            'resolution_no' => '1',
            'resolution_title' => 'Keyword test',
            'series' => 2026,
            'status' => 'draft',
            'keyword' => 'AlphaTag',
            'created_by' => $user->id,
        ]);

        Cache::forget('splis.keywords.used');

        $this->actingAs($user)
            ->getJson(route('resolutions.keywords'))
            ->assertOk()
            ->assertJsonFragment(['AlphaTag']);

        $this->assertTrue(Cache::has('splis.keywords.used'));

        Resolution::query()->first()?->update(['keyword' => 'BetaTag']);
        IncomingFieldOptions::forgetKeywordCache();

        $this->actingAs($user)
            ->getJson(route('resolutions.keywords'))
            ->assertOk()
            ->assertJsonFragment(['BetaTag'])
            ->assertJsonMissing(['AlphaTag']);
    }

    public function test_past_session_hides_maker_link_on_order_of_business_index(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $pastSession = LegislativeSession::create([
            'session_date' => now()->subDay(),
            'session_kind' => 'regular',
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);
        ObDocument::create([
            'legislative_session_id' => $pastSession->id,
            'title' => 'Past OB',
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);

        $futureSession = LegislativeSession::create([
            'session_date' => now()->addWeek(),
            'session_kind' => 'regular',
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);
        ObDocument::create([
            'legislative_session_id' => $futureSession->id,
            'title' => 'Future OB',
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('ob.sessions.index'));

        $response->assertOk();
        $body = $response->getContent() ?: '';

        $this->assertStringContainsString(route('ob.document.maker', $futureSession), $body);
        $this->assertStringNotContainsString(route('ob.document.maker', $pastSession), $body);
        $this->assertStringContainsString(route('ob.sessions.attendance', $pastSession), $body);
    }

    public function test_role_permissions_page_is_superadmin_only(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.role-permissions.index'))
            ->assertForbidden();

        $this->actingAs($superadmin)
            ->get(route('admin.role-permissions.index'))
            ->assertOk()
            ->assertSee('Role permissions')
            ->assertSee('Resolutions');
    }
}

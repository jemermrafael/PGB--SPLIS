<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\User;
use App\Support\UserCapability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserModuleCapabilitiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_null_capabilities_mean_all_modules_for_encoder(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Encoder,
            'is_active' => true,
            'capabilities' => null,
        ]);

        $this->assertTrue($user->hasModuleCapability(UserCapability::AGENDA));
        $this->assertTrue($user->hasModuleCapability(UserCapability::RESOLUTIONS));
        $this->assertSame(UserCapability::keys(), $user->effectiveCapabilities());
    }

    public function test_encoder_can_be_limited_to_selected_modules(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Encoder,
            'is_active' => true,
            'capabilities' => [UserCapability::AGENDA, UserCapability::ORDER_OF_BUSINESS],
        ]);

        $this->assertTrue($user->hasModuleCapability(UserCapability::AGENDA));
        $this->assertTrue($user->hasModuleCapability(UserCapability::ORDER_OF_BUSINESS));
        $this->assertFalse($user->hasModuleCapability(UserCapability::RESOLUTIONS));
        $this->assertFalse($user->hasModuleCapability(UserCapability::ORDINANCES));
    }

    public function test_admin_always_has_all_modules(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Admin,
            'is_active' => true,
            'capabilities' => [UserCapability::AGENDA],
        ]);

        $this->assertTrue($user->hasModuleCapability(UserCapability::RESOLUTIONS));
        $this->assertSame(UserCapability::keys(), $user->effectiveCapabilities());
    }

    public function test_superadmin_can_save_encoder_capabilities(): void
    {
        $superadmin = User::factory()->create([
            'role' => UserRole::Superadmin,
            'is_active' => true,
            'username' => 'super1',
        ]);

        $this->actingAs($superadmin)
            ->post(route('users.store'), [
                'name' => 'Agenda Only Encoder',
                'username' => 'agenda_only',
                'email' => 'agenda-only@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'role' => UserRole::Encoder->value,
                'is_active' => '1',
                'capabilities' => [UserCapability::AGENDA, UserCapability::ORDER_OF_BUSINESS],
            ])
            ->assertRedirect(route('users.index'));

        $encoder = User::query()->where('username', 'agenda_only')->first();
        $this->assertNotNull($encoder);
        $this->assertSame(
            [UserCapability::AGENDA, UserCapability::ORDER_OF_BUSINESS],
            $encoder->capabilities,
        );
    }

    public function test_resolution_only_encoder_cannot_create_agenda(): void
    {
        $encoder = User::factory()->create([
            'role' => UserRole::Encoder,
            'is_active' => true,
            'capabilities' => [UserCapability::RESOLUTIONS],
        ]);

        $this->assertFalse($encoder->can('create', AgendaItem::class));
        $this->assertTrue($encoder->can('create', \App\Models\Resolution::class));

        $this->actingAs($encoder)
            ->get(route('agenda.create'))
            ->assertForbidden();

        $this->actingAs($encoder)
            ->get(route('resolutions.create'))
            ->assertOk();
    }

    public function test_ob_only_encoder_can_view_agenda_list_but_not_edit(): void
    {
        $encoder = User::factory()->create([
            'role' => UserRole::Encoder,
            'is_active' => true,
            'capabilities' => [UserCapability::ORDER_OF_BUSINESS, UserCapability::COMMITTEE_REPORTS],
        ]);

        $agenda = AgendaItem::query()->create([
            'tracking_no' => '501',
            'title' => 'OB placement only',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'created_by' => $encoder->id,
        ]);

        $this->assertTrue($encoder->can('viewAny', AgendaItem::class));
        $this->assertFalse($encoder->can('update', $agenda));
        $this->assertTrue($encoder->can('addToOrderOfBusiness', $agenda));

        $this->actingAs($encoder)
            ->get(route('agenda.edit', $agenda))
            ->assertForbidden();
    }
}

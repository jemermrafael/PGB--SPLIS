<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeaderActionsMarkupTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_menu_does_not_use_icon_only_button_class_with_role_label(): void
    {
        $user = User::factory()->create([
            'role' => 'superadmin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();

        $html = $response->getContent();
        $this->assertIsString($html);

        $this->assertMatchesRegularExpression(
            '/class="splis-header-btn splis-user-menu-trigger"/',
            $html,
            'User menu should use text button classes without splis-header-btn-icon.',
        );

        $this->assertDoesNotMatchRegularExpression(
            '/splis-user-menu-trigger splis-header-btn-icon|splis-header-btn-icon splis-user-menu-trigger/',
            $html,
        );
    }
}

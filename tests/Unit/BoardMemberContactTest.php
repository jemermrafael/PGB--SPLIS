<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\BoardMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoardMemberContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_email_uses_linked_user_account(): void
    {
        $member = BoardMember::query()->create([
            'name' => 'Juan Dela Cruz',
            'email' => 'old@example.com',
            'mobile_number' => '09171234567',
        ]);

        User::factory()->create([
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@bataan.gov.ph',
            'role' => UserRole::BoardMember,
            'board_member_id' => $member->id,
            'is_active' => true,
        ]);

        $member->load('user');

        $this->assertSame('09171234567', $member->contactNumber());
        $this->assertSame('juan@bataan.gov.ph', $member->contactEmail());
    }

    public function test_contact_email_falls_back_to_board_member_record_when_unlinked(): void
    {
        $member = BoardMember::query()->create([
            'name' => 'Maria Santos',
            'email' => 'maria@example.com',
            'mobile_number' => '09998887777',
        ]);

        $this->assertSame('maria@example.com', $member->contactEmail());
    }
}

<?php

namespace Tests\Feature;

use App\Enums\ObBlockType;
use App\Models\LegislativeSession;
use App\Models\ObBlock;
use App\Models\ObDocument;
use App\Models\User;
use App\Services\ObDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObAppearanceGuestsSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_appearance_guests_syncs_to_session_attendance_guests(): void
    {
        $user = User::factory()->create();

        $session = LegislativeSession::query()->create([
            'session_date' => now()->toDateString(),
            'session_kind' => 'regular',
            'status' => 'scheduled',
            'guests' => [
                ['name' => 'Existing Extra', 'remarks' => 'Attendance only'],
            ],
            'created_by' => $user->id,
        ]);

        $document = ObDocument::query()->create([
            'legislative_session_id' => $session->id,
            'title' => 'OB',
            'status' => ObDocument::STATUS_DRAFT,
            'created_by' => $user->id,
        ]);

        $block = ObBlock::query()->create([
            'ob_document_id' => $document->id,
            'type' => ObBlockType::RomanSection,
            'sort_order' => 1,
            'content' => [
                'numeral' => 'II.',
                'title' => 'APPEARANCE OF GUEST/S',
                'guests' => [],
            ],
        ]);

        app(ObDocumentService::class)->updateBlock($block, [
            'numeral' => 'II.',
            'title' => 'APPEARANCE OF GUEST/S',
            'guests' => [
                ['name' => 'Hon. Guest One'],
                ['name' => ''],
                ['name' => 'Hon. Guest Two'],
            ],
        ]);

        $session->refresh();
        $block->refresh();

        $this->assertSame([
            ['name' => 'Hon. Guest One'],
            ['name' => ''],
            ['name' => 'Hon. Guest Two'],
        ], $block->content['guests']);

        $this->assertSame([
            ['name' => 'Hon. Guest One', 'remarks' => ''],
            ['name' => 'Hon. Guest Two', 'remarks' => ''],
            ['name' => 'Existing Extra', 'remarks' => 'Attendance only'],
        ], $session->guests);
    }

    public function test_attendance_page_pulls_guests_from_ob_appearance_section(): void
    {
        $user = User::factory()->create([
            'role' => \App\Enums\UserRole::Admin,
            'is_active' => true,
        ]);

        $session = LegislativeSession::query()->create([
            'session_date' => now()->toDateString(),
            'session_kind' => 'regular',
            'status' => 'scheduled',
            'created_by' => $user->id,
        ]);

        $document = ObDocument::query()->create([
            'legislative_session_id' => $session->id,
            'title' => 'OB',
            'status' => ObDocument::STATUS_DRAFT,
            'created_by' => $user->id,
        ]);

        ObBlock::query()->create([
            'ob_document_id' => $document->id,
            'type' => ObBlockType::RomanSection,
            'sort_order' => 1,
            'content' => [
                'numeral' => 'II.',
                'title' => 'APPEARANCE OF GUEST/S',
                'guests' => [
                    ['name' => 'OB Guest A'],
                    ['name' => 'OB Guest B'],
                ],
            ],
        ]);

        $this->actingAs($user)
            ->get(route('ob.sessions.attendance', $session))
            ->assertOk()
            ->assertSee('OB Guest A')
            ->assertSee('OB Guest B')
            ->assertSee('Add Guest');

        $this->assertSame('OB Guest A', $session->fresh()->guests[0]['name']);
        $this->assertSame('OB Guest B', $session->fresh()->guests[1]['name']);
    }
}

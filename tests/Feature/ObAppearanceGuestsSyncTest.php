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
                ['name' => 'Existing Extra', 'remarks' => 'Attendance only', 'source' => 'attendance'],
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
            ['name' => 'Hon. Guest One', 'remarks' => '', 'source' => 'ob'],
            ['name' => 'Hon. Guest Two', 'remarks' => '', 'source' => 'ob'],
            ['name' => 'Existing Extra', 'remarks' => 'Attendance only', 'source' => 'attendance'],
        ], $session->guests);
    }

    public function test_removing_ob_guest_from_maker_drops_official_attendance_entry(): void
    {
        $user = User::factory()->create();

        $session = LegislativeSession::query()->create([
            'session_date' => now()->toDateString(),
            'session_kind' => 'regular',
            'status' => 'scheduled',
            'guests' => [
                ['name' => 'Keep Official', 'remarks' => '', 'source' => 'ob'],
                ['name' => 'Drop Official', 'remarks' => 'was on OB', 'source' => 'ob'],
                ['name' => 'Info Only', 'remarks' => 'attendance', 'source' => 'attendance'],
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
                'guests' => [
                    ['name' => 'Keep Official'],
                    ['name' => 'Drop Official'],
                ],
            ],
        ]);

        app(ObDocumentService::class)->updateBlock($block, [
            'numeral' => 'II.',
            'title' => 'APPEARANCE OF GUEST/S',
            'guests' => [
                ['name' => 'Keep Official'],
            ],
        ]);

        $this->assertSame([
            ['name' => 'Keep Official', 'remarks' => '', 'source' => 'ob'],
            ['name' => 'Info Only', 'remarks' => 'attendance', 'source' => 'attendance'],
        ], $session->fresh()->guests);
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
            ->assertSee('Add Guest')
            ->assertSee('OB official');

        $this->assertSame('OB Guest A', $session->fresh()->guests[0]['name']);
        $this->assertSame('ob', $session->fresh()->guests[0]['source']);
        $this->assertSame('OB Guest B', $session->fresh()->guests[1]['name']);
    }

    public function test_removing_official_guest_on_attendance_updates_ob_section_ii(): void
    {
        $user = User::factory()->create([
            'role' => \App\Enums\UserRole::Admin,
            'is_active' => true,
        ]);

        $session = LegislativeSession::query()->create([
            'session_date' => now()->toDateString(),
            'session_kind' => 'regular',
            'status' => 'scheduled',
            'guests' => [
                ['name' => 'Official Keep', 'remarks' => '', 'source' => 'ob'],
                ['name' => 'Official Remove', 'remarks' => '', 'source' => 'ob'],
                ['name' => 'Info Keep', 'remarks' => 'notes', 'source' => 'attendance'],
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
                'guests' => [
                    ['name' => 'Official Keep'],
                    ['name' => 'Official Remove'],
                ],
            ],
        ]);

        $this->actingAs($user)
            ->put(route('ob.sessions.attendance.update', $session), [
                'guests' => [
                    ['name' => 'Official Keep', 'remarks' => ''],
                    ['name' => 'Info Keep', 'remarks' => 'notes'],
                ],
            ])
            ->assertRedirect(route('ob.sessions.attendance', $session));

        $this->assertSame([
            ['name' => 'Official Keep', 'remarks' => '', 'source' => 'ob'],
            ['name' => 'Info Keep', 'remarks' => 'notes', 'source' => 'attendance'],
        ], $session->fresh()->guests);

        $this->assertSame([
            ['name' => 'Official Keep'],
        ], $block->fresh()->content['guests']);
    }

    public function test_sync_drops_incomplete_empty_remark_prefixes_from_attendance(): void
    {
        $user = User::factory()->create();

        $session = LegislativeSession::query()->create([
            'session_date' => now()->toDateString(),
            'session_kind' => 'regular',
            'status' => 'scheduled',
            'guests' => [
                ['name' => 'Juan', 'remarks' => ''],
                ['name' => 'Juan Dela', 'remarks' => ''],
                ['name' => 'Juan Dela Cruz', 'remarks' => ''],
                ['name' => 'Jemer M. Rafae', 'remarks' => ''],
                ['name' => 'Jemer M. Rafael', 'remarks' => ''],
                ['name' => 'Kept Extra', 'remarks' => 'manual'],
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
                'guests' => [
                    ['name' => 'Juan Dela Cruz'],
                ],
            ],
        ]);

        app(ObDocumentService::class)->updateBlock($block, [
            'numeral' => 'II.',
            'title' => 'APPEARANCE OF GUEST/S',
            'guests' => [
                ['name' => 'Juan Dela Cruz'],
            ],
        ]);

        $this->assertSame([
            ['name' => 'Juan Dela Cruz', 'remarks' => '', 'source' => 'ob'],
            ['name' => 'Jemer M. Rafael', 'remarks' => '', 'source' => 'attendance'],
            ['name' => 'Kept Extra', 'remarks' => 'manual', 'source' => 'attendance'],
        ], $session->fresh()->guests);
    }
}

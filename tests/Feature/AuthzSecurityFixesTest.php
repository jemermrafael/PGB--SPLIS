<?php

namespace Tests\Feature;

use App\Enums\CommitteeMembershipRole;
use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\BoardMember;
use App\Models\Committee;
use App\Models\CommitteeMembership;
use App\Models\CommitteeTerm;
use App\Models\IncomingDocument;
use App\Models\ObDocument;
use App\Models\User;
use App\Policies\CommitteePolicy;
use App\Policies\LegislativeSessionPolicy;
use App\Policies\ObDocumentPolicy;
use App\Policies\ReferenceMaterialPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthzSecurityFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_deactivated_user_is_logged_out_on_next_request(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Encoder,
            'is_active' => true,
            'username' => 'encoder_active',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();

        $user->update(['is_active' => false]);

        $this->actingAs($user->fresh())
            ->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_deactivating_user_clears_remember_token(): void
    {
        $superadmin = User::factory()->create([
            'role' => UserRole::Superadmin,
            'is_active' => true,
            'username' => 'super_authz',
        ]);

        $target = User::factory()->create([
            'role' => UserRole::Encoder,
            'is_active' => true,
            'username' => 'to_deactivate',
            'email' => 'to-deactivate@example.com',
            'name' => 'To Deactivate',
            'remember_token' => 'remember-me-token',
        ]);

        $this->actingAs($superadmin)
            ->put(route('users.update', $target), [
                'name' => $target->name,
                'username' => $target->username,
                'email' => $target->email,
                'role' => UserRole::Encoder->value,
                // is_active omitted → boolean false
            ])
            ->assertRedirect(route('users.index'));

        $this->assertFalse($target->fresh()->is_active);
        $this->assertNull($target->fresh()->remember_token);
    }

    public function test_guest_role_is_denied_open_read_policies(): void
    {
        $guest = User::factory()->create([
            'role' => UserRole::Guest,
            'is_active' => true,
            'username' => 'guest_user',
        ]);

        $this->assertFalse((new LegislativeSessionPolicy)->viewAny($guest));
        $this->assertFalse((new CommitteePolicy)->viewAny($guest));
        $this->assertFalse((new ReferenceMaterialPolicy)->viewAny($guest));
        $this->assertFalse((new ObDocumentPolicy)->view($guest, new ObDocument));

        $this->actingAs($guest)
            ->get(route('ob.sessions.index'))
            ->assertForbidden();

        $this->actingAs($guest)
            ->get(route('committees.index'))
            ->assertForbidden();

        $this->actingAs($guest)
            ->get(route('board-members.index'))
            ->assertForbidden();
    }

    public function test_incoming_reads_require_encode_capability(): void
    {
        config(['incoming.enabled' => true]);

        $incoming = IncomingDocument::query()->create([
            'source' => IncomingDocument::SOURCE_MANUAL,
            'link_status' => IncomingDocument::LINK_UNLINKED,
            'title' => 'Municipal request',
            'municipality' => 'Orani',
        ]);

        $boardMember = BoardMember::query()->create([
            'name' => 'Watchlist BM',
            'honorific' => 'Hon.',
            'is_active' => true,
        ]);

        $bm = User::factory()->create([
            'role' => UserRole::BoardMember,
            'board_member_id' => $boardMember->id,
            'is_active' => true,
            'username' => 'bm_incoming',
        ]);

        $guest = User::factory()->create([
            'role' => UserRole::Guest,
            'is_active' => true,
            'username' => 'guest_incoming',
        ]);

        $encoder = User::factory()->create([
            'role' => UserRole::Encoder,
            'is_active' => true,
            'username' => 'encoder_incoming',
            'capabilities' => null,
        ]);

        foreach ([$bm, $guest] as $user) {
            $this->actingAs($user)
                ->get(route('incoming.index'))
                ->assertForbidden();

            $this->actingAs($user)
                ->getJson(route('incoming.search'))
                ->assertForbidden();

            $this->actingAs($user)
                ->get(route('incoming.show', $incoming))
                ->assertForbidden();
        }

        $this->actingAs($encoder)
            ->get(route('incoming.index'))
            ->assertOk();

        $this->actingAs($encoder)
            ->getJson(route('incoming.search'))
            ->assertOk();
    }

    public function test_board_member_cannot_watchlist_inaccessible_agenda(): void
    {
        $term = CommitteeTerm::query()->create([
            'label' => '2025–2028',
            'year_from' => 2025,
            'year_to' => 2028,
            'is_current' => true,
        ]);

        $boardMember = BoardMember::query()->create([
            'name' => 'Scoped Watcher',
            'honorific' => 'Hon.',
            'is_active' => true,
        ]);

        $ownCommittee = Committee::query()->create([
            'name' => 'Housing and Land Use',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $outsideCommittee = Committee::query()->create([
            'name' => 'Public Information',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        CommitteeMembership::query()->create([
            'committee_id' => $ownCommittee->id,
            'board_member_id' => $boardMember->id,
            'committee_term_id' => $term->id,
            'role' => CommitteeMembershipRole::Chair,
            'sort_order' => 0,
        ]);

        $user = User::factory()->create([
            'role' => UserRole::BoardMember,
            'board_member_id' => $boardMember->id,
            'is_active' => true,
            'username' => 'bm_watchlist',
        ]);

        $outsideAgenda = AgendaItem::query()->create([
            'title' => 'Outside committee agenda',
            'committee_referred' => $outsideCommittee->name,
            'status' => AgendaItem::STATUS_PENDING,
            'date_of_referral' => now()->toDateString(),
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);

        $ownAgenda = AgendaItem::query()->create([
            'title' => 'Own committee agenda',
            'committee_referred' => $ownCommittee->name,
            'status' => AgendaItem::STATUS_PENDING,
            'date_of_referral' => now()->toDateString(),
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('board-member.watchlist.store'), [
                'watchable_type' => 'agenda',
                'watchable_id' => $outsideAgenda->id,
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->from(route('board-member.watchlist.index'))
            ->post(route('board-member.watchlist.store'), [
                'watchable_type' => 'agenda',
                'watchable_id' => $ownAgenda->id,
            ])
            ->assertRedirect(route('board-member.watchlist.index'));
    }
}

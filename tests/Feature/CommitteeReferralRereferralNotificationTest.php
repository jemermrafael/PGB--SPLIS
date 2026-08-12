<?php

namespace Tests\Feature;

use App\Enums\CommitteeMembershipRole;
use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\BoardMember;
use App\Models\Committee;
use App\Models\CommitteeMembership;
use App\Models\CommitteeTerm;
use App\Models\Municipality;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\BoardMemberNotifier;
use App\Services\MunicipalNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CommitteeReferralRereferralNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_board_member_no_longer_gets_immediate_committee_referral_alerts(): void
    {
        Mail::fake();

        [$term, $oldCommittee, $oldChair, $newCommittee, $newChair] = $this->twoChairs();

        $encoder = User::factory()->create([
            'role' => UserRole::Encoder,
            'is_active' => true,
        ]);

        $agenda = AgendaItem::query()->create([
            'title' => 'Needs a better committee',
            'tracking_no' => '501',
            'committee_referred' => $oldCommittee->name,
            'status' => AgendaItem::STATUS_PENDING,
            'date_of_referral' => now()->toDateString(),
            'date_received' => now()->toDateString(),
            'prescribed_days' => 0,
            'sender' => 'Mariveles',
            'created_by' => $encoder->id,
        ]);

        app(BoardMemberNotifier::class)->notifyCommitteeReferral($agenda);
        $agenda->update(['committee_referred' => $newCommittee->name]);
        app(BoardMemberNotifier::class)->notifyCommitteeReferral($agenda, $oldCommittee->name);

        $this->assertSame(
            0,
            UserNotification::query()
                ->whereIn('user_id', [$oldChair->id, $newChair->id])
                ->where('type', UserNotification::TYPE_COMMITTEE_REFERRAL)
                ->count()
        );
        Mail::assertNothingSent();
    }

    public function test_agenda_update_does_not_create_immediate_board_member_referral_alerts(): void
    {
        Mail::fake();

        [, $committee, $chair] = $this->oneChair();

        $encoder = User::factory()->create([
            'role' => UserRole::Encoder,
            'is_active' => true,
        ]);

        $agenda = AgendaItem::query()->create([
            'title' => 'Already referred',
            'tracking_no' => '502',
            'committee_referred' => $committee->name,
            'status' => AgendaItem::STATUS_PENDING,
            'date_of_referral' => now()->toDateString(),
            'date_received' => now()->toDateString(),
            'prescribed_days' => 0,
            'sender' => 'PGO',
            'created_by' => $encoder->id,
        ]);

        app(BoardMemberNotifier::class)->notifyCommitteeReferral($agenda);
        $this->assertSame(0, UserNotification::query()->where('user_id', $chair->id)->count());

        $this->actingAs($encoder)
            ->put(route('agenda.update', $agenda), [
                'title' => 'Already referred (edited title only)',
                'tracking_no' => '502',
                'committee_referred' => $committee->name,
                'status' => AgendaItem::STATUS_PENDING,
                'date_of_referral' => now()->toDateString(),
                'date_received' => now()->toDateString(),
                'prescribed_days' => 0,
                'sender' => 'PGO',
            ])
            ->assertRedirect();

        $this->assertSame(0, UserNotification::query()->where('user_id', $chair->id)->count());
        Mail::assertNothingSent();
    }

    public function test_municipal_viewer_no_longer_gets_immediate_committee_referral_alerts(): void
    {
        Mail::fake();

        $municipality = Municipality::query()->create([
            'description' => 'Mariveles',
            'code' => 301,
        ]);
        $viewer = User::factory()->create([
            'role' => UserRole::MunicipalViewer,
            'municipality_id' => $municipality->id,
            'is_active' => true,
            'email' => 'mariveles_'.uniqid().'@example.com',
        ]);

        $agenda = AgendaItem::query()->create([
            'title' => 'Municipal request',
            'tracking_no' => '503',
            'committee_referred' => 'Tourism',
            'status' => AgendaItem::STATUS_PENDING,
            'date_of_referral' => now()->toDateString(),
            'date_received' => now()->toDateString(),
            'prescribed_days' => 0,
            'sender' => 'Mariveles',
            'created_by' => User::factory()->create(['role' => UserRole::Encoder])->id,
        ]);

        $notifier = app(MunicipalNotifier::class);
        $notifier->notifyCommitteeReferral($agenda);
        $agenda->update(['committee_referred' => 'Health and Sanitation']);
        $notifier->notifyCommitteeReferral($agenda->fresh(), 'Tourism');

        $this->assertSame(
            0,
            UserNotification::query()
                ->where('user_id', $viewer->id)
                ->where('type', UserNotification::TYPE_COMMITTEE_REFERRAL)
                ->count()
        );
        Mail::assertNothingSent();
    }

    /**
     * @return array{0: CommitteeTerm, 1: Committee, 2: User, 3: Committee, 4: User}
     */
    protected function twoChairs(): array
    {
        $term = CommitteeTerm::query()->create([
            'label' => '2025–2028',
            'year_from' => 2025,
            'year_to' => 2028,
            'is_current' => true,
        ]);

        $oldCommittee = Committee::query()->create([
            'name' => 'Public Information',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $oldProfile = BoardMember::query()->create([
            'name' => 'Old Chair',
            'honorific' => 'Hon.',
            'is_active' => true,
        ]);
        CommitteeMembership::query()->create([
            'committee_id' => $oldCommittee->id,
            'board_member_id' => $oldProfile->id,
            'committee_term_id' => $term->id,
            'role' => CommitteeMembershipRole::Chair,
            'sort_order' => 0,
        ]);
        $oldChair = User::factory()->create([
            'role' => UserRole::BoardMember,
            'board_member_id' => $oldProfile->id,
            'username' => 'bm_old_'.uniqid(),
            'email' => 'bm_old_'.uniqid().'@example.com',
            'is_active' => true,
        ]);

        $newCommittee = Committee::query()->create([
            'name' => 'Tourism',
            'is_active' => true,
            'sort_order' => 2,
        ]);
        $newProfile = BoardMember::query()->create([
            'name' => 'New Chair',
            'honorific' => 'Hon.',
            'is_active' => true,
        ]);
        CommitteeMembership::query()->create([
            'committee_id' => $newCommittee->id,
            'board_member_id' => $newProfile->id,
            'committee_term_id' => $term->id,
            'role' => CommitteeMembershipRole::Chair,
            'sort_order' => 0,
        ]);
        $newChair = User::factory()->create([
            'role' => UserRole::BoardMember,
            'board_member_id' => $newProfile->id,
            'username' => 'bm_new_'.uniqid(),
            'email' => 'bm_new_'.uniqid().'@example.com',
            'is_active' => true,
        ]);

        return [$term, $oldCommittee, $oldChair, $newCommittee, $newChair];
    }

    /**
     * @return array{0: CommitteeTerm, 1: Committee, 2: User}
     */
    protected function oneChair(): array
    {
        $term = CommitteeTerm::query()->create([
            'label' => '2025–2028',
            'year_from' => 2025,
            'year_to' => 2028,
            'is_current' => true,
        ]);
        $committee = Committee::query()->create([
            'name' => 'Housing and Land Use',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $profile = BoardMember::query()->create([
            'name' => 'Solo Chair',
            'honorific' => 'Hon.',
            'is_active' => true,
        ]);
        CommitteeMembership::query()->create([
            'committee_id' => $committee->id,
            'board_member_id' => $profile->id,
            'committee_term_id' => $term->id,
            'role' => CommitteeMembershipRole::Chair,
            'sort_order' => 0,
        ]);
        $chair = User::factory()->create([
            'role' => UserRole::BoardMember,
            'board_member_id' => $profile->id,
            'username' => 'bm_solo_'.uniqid(),
            'email' => 'bm_solo_'.uniqid().'@example.com',
            'is_active' => true,
        ]);

        return [$term, $committee, $chair];
    }
}

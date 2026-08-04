<?php

namespace App\Services;

use App\Enums\CommitteeMembershipRole;
use App\Enums\ObBlockType;
use App\Models\AgendaItem;
use App\Models\Committee;
use App\Models\CommitteeMembership;
use App\Models\CommitteeReferralDelivery;
use App\Models\CommitteeTerm;
use App\Models\LegislativeSession;
use App\Models\ScheduledCommitteeReferral;
use App\Models\User;
use App\Support\CommitteeLookup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CommitteeReferralScheduleService
{
    public function __construct(
        protected BoardMemberNotifier $notifier,
    ) {}

    /**
     * Regular unassigned agendas on a session OB, with target committee + chair.
     *
     * @return Collection<int, array{
     *   agenda: AgendaItem,
     *   committee: ?Committee,
     *   chair: ?\App\Models\BoardMember,
     *   chair_user: ?User
     * }>
     */
    public function previewForSession(LegislativeSession $session): Collection
    {
        $session->loadMissing(['obDocument.blocks.agendaItem']);

        $blocks = $session->obDocument?->blocks ?? collect();

        return $blocks
            ->filter(fn ($block) => $block->type === ObBlockType::UnassignedAgenda)
            ->filter(fn ($block) => ($block->content['kind'] ?? 'regular') !== 'urgent')
            ->map(function ($block) {
                /** @var AgendaItem|null $agenda */
                $agenda = $block->agendaItem;
                if ($agenda === null) {
                    return null;
                }

                $committee = $this->resolveCommittee($agenda, is_array($block->content) ? $block->content : []);
                $chair = $committee ? $this->chairForCommittee($committee) : null;
                $chairUser = $chair?->user;

                return [
                    'agenda' => $agenda,
                    'committee' => $committee,
                    'chair' => $chair,
                    'chair_user' => $chairUser instanceof User ? $chairUser : null,
                ];
            })
            ->filter()
            ->unique(fn (array $row) => $row['agenda']->id)
            ->values();
    }

    public function schedule(
        LegislativeSession $session,
        \DateTimeInterface $scheduledAt,
        User $actor,
        ?string $notes = null,
        bool $sendEmail = false,
    ): ScheduledCommitteeReferral {
        $preview = $this->previewForSession($session);

        if ($preview->isEmpty()) {
            throw new \InvalidArgumentException('This session has no Regular Unassigned Business agendas to schedule.');
        }

        return ScheduledCommitteeReferral::query()->create([
            'legislative_session_id' => $session->id,
            'scheduled_at' => $scheduledAt,
            'status' => ScheduledCommitteeReferral::STATUS_PENDING,
            'created_by' => $actor->id,
            'notes' => filled($notes) ? trim($notes) : null,
            'send_email' => $sendEmail,
        ]);
    }

    public function cancel(ScheduledCommitteeReferral $schedule): void
    {
        if (! $schedule->isPending()) {
            return;
        }

        $schedule->forceFill([
            'status' => ScheduledCommitteeReferral::STATUS_CANCELLED,
        ])->save();
    }

    /**
     * @return int Number of schedules dispatched
     */
    public function dispatchDue(?\DateTimeInterface $asOf = null): int
    {
        $asOf ??= now();

        $due = ScheduledCommitteeReferral::query()
            ->where('status', ScheduledCommitteeReferral::STATUS_PENDING)
            ->where('scheduled_at', '<=', $asOf)
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get();

        $count = 0;

        foreach ($due as $schedule) {
            $this->dispatch($schedule);
            $count++;
        }

        return $count;
    }

    public function dispatch(ScheduledCommitteeReferral $schedule): void
    {
        if (! $schedule->isPending()) {
            return;
        }

        $session = $schedule->legislativeSession;
        if ($session === null) {
            $schedule->forceFill([
                'status' => ScheduledCommitteeReferral::STATUS_CANCELLED,
            ])->save();

            return;
        }

        $preview = $this->previewForSession($session);

        DB::transaction(function () use ($schedule, $preview): void {
            foreach ($preview as $row) {
                /** @var AgendaItem $agenda */
                $agenda = $row['agenda'];
                $committee = $row['committee'];
                $chair = $row['chair'];
                $chairUser = $row['chair_user'];

                if ($committee === null || $chair === null || $chairUser === null || ! $chairUser->is_active) {
                    continue;
                }

                $delivery = CommitteeReferralDelivery::query()->firstOrCreate(
                    [
                        'scheduled_committee_referral_id' => $schedule->id,
                        'agenda_item_id' => $agenda->id,
                        'board_member_id' => $chair->id,
                    ],
                    [
                        'committee_id' => $committee->id,
                        'delivered_at' => now(),
                    ],
                );

                if ($delivery->delivered_at === null) {
                    $delivery->forceFill(['delivered_at' => now()])->save();
                }

                $this->notifier->notifyScheduledCommitteeReferralToChair(
                    $agenda,
                    $committee,
                    $chairUser,
                    (bool) $schedule->send_email,
                );
            }

            $schedule->forceFill([
                'status' => ScheduledCommitteeReferral::STATUS_SENT,
                'sent_at' => now(),
            ])->save();
        });
    }

    /**
     * Regular Unassigned agendas from the latest OB whose referrals are unlocked
     * (2 hours after session date/time), for committees this member chairs.
     * Does not require Schedule Committee Referral.
     *
     * @return array{agendas: Collection<int, AgendaItem>, session: ?LegislativeSession}
     */
    public function referredFromLastObForChair(User $user): array
    {
        $empty = ['agendas' => collect(), 'session' => null];

        $boardMemberId = (int) ($user->board_member_id ?? 0);
        if ($boardMemberId < 1 || ! $user->isBoardMember()) {
            return $empty;
        }

        $sessions = LegislativeSession::query()
            ->visibleToBoardMembers()
            ->whereNotNull('session_date')
            ->orderByDesc('session_date')
            ->orderByDesc('session_time')
            ->orderByDesc('id')
            ->limit(40)
            ->get();

        foreach ($sessions as $session) {
            if (! $session->committeeReferralsAreAvailable()) {
                continue;
            }

            $agendas = $this->previewForSession($session)
                ->filter(fn (array $row) => ($row['chair']?->id ?? null) === $boardMemberId)
                ->map(fn (array $row) => $row['agenda'])
                ->filter()
                ->unique(fn (AgendaItem $agenda) => $agenda->id)
                ->values();

            if ($agendas->isEmpty()) {
                continue;
            }

            return [
                'agendas' => $agendas,
                'session' => $session,
            ];
        }

        return $empty;
    }

    /**
     * @return Collection<int, AgendaItem>
     */
    public function incomingForChair(User $user): Collection
    {
        return $this->referredFromLastObForChair($user)['agendas'];
    }

    /**
     * @param  array<string, mixed>  $content
     */
    protected function resolveCommittee(AgendaItem $agenda, array $content): ?Committee
    {
        if (filled($agenda->committee_referred)) {
            return CommitteeLookup::findByName((string) $agenda->committee_referred);
        }

        $committeeId = $content['committee_id'] ?? null;
        if (is_numeric($committeeId) && (int) $committeeId > 0) {
            return CommitteeLookup::findById((int) $committeeId);
        }

        return null;
    }

    protected function chairForCommittee(Committee $committee): ?\App\Models\BoardMember
    {
        $termId = CommitteeTerm::query()->current()->orderByDesc('id')->value('id');

        $membership = CommitteeMembership::query()
            ->with('boardMember.user')
            ->where('committee_id', $committee->id)
            ->where('role', CommitteeMembershipRole::Chair)
            ->when(
                $termId,
                fn ($query) => $query->where(function ($inner) use ($termId): void {
                    $inner->where('committee_term_id', $termId)
                        ->orWhereNull('committee_term_id');
                }),
            )
            ->orderByDesc('committee_term_id')
            ->orderByDesc('id')
            ->first();

        if ($membership?->boardMember !== null) {
            return $membership->boardMember;
        }

        // Fallback when multiple "current" terms exist or roster is on another term.
        return CommitteeMembership::query()
            ->with('boardMember.user')
            ->where('committee_id', $committee->id)
            ->where('role', CommitteeMembershipRole::Chair)
            ->orderByDesc('committee_term_id')
            ->orderByDesc('id')
            ->first()
            ?->boardMember;
    }
}

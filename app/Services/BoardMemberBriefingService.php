<?php

namespace App\Services;

use App\Models\AgendaItem;
use App\Models\LegislativeSession;
use App\Models\User;
use Illuminate\Support\Collection;

class BoardMemberBriefingService
{
    public function __construct(
        protected BoardMemberDashboardService $dashboard,
        protected CommitteeReferralScheduleService $referralSchedule,
    ) {}

    /**
     * @return array{
     *     next_session: LegislativeSession|null,
     *     my_items_on_next_ob: Collection<int, AgendaItem>,
     *     deadline_agendas: Collection<int, AgendaItem>,
     *     deadline_count: int,
     *     deadline_days: int,
     *     unread_notifications: int,
     *     pending_count: int,
     *     session_today: bool,
     *     incoming_for_referral: Collection<int, AgendaItem>,
     *     referred_from_last_ob: Collection<int, AgendaItem>,
     *     referred_from_session: LegislativeSession|null
     * }
     */
    public function for(User $user): array
    {
        $deadlineDays = $this->dashboard->expiringSoonDays();

        if ($user->board_member_id === null) {
            return [
                'next_session' => null,
                'my_items_on_next_ob' => collect(),
                'deadline_agendas' => collect(),
                'deadline_count' => 0,
                'deadline_days' => $deadlineDays,
                'unread_notifications' => 0,
                'pending_count' => 0,
                'session_today' => false,
                'incoming_for_referral' => collect(),
                'referred_from_last_ob' => collect(),
                'referred_from_session' => null,
            ];
        }

        $nextSession = $this->nextSession();
        $myItems = $nextSession
            ? $this->dashboard->myCommitteeItemsOnSession($user, $nextSession)
            : collect();

        $stats = $this->dashboard->agendaStatsFor($user);
        $referred = $this->referralSchedule->referredFromLastObForChair($user);

        return [
            'next_session' => $nextSession,
            'my_items_on_next_ob' => $myItems,
            'deadline_agendas' => $this->dashboard->expiringSoonAgendasFor($user, 12),
            'deadline_count' => $stats['expiring_soon'] ?? 0,
            'deadline_days' => $deadlineDays,
            'unread_notifications' => $user->unreadNotifications()->count(),
            'pending_count' => $stats['pending'] ?? 0,
            'session_today' => $nextSession?->session_date?->isToday() ?? false,
            'incoming_for_referral' => $referred['agendas'],
            'referred_from_last_ob' => $referred['agendas'],
            'referred_from_session' => $referred['session'],
        ];
    }

    public function nextSession(): ?LegislativeSession
    {
        return LegislativeSession::query()
            ->with(['obDocument.blocks.agendaItem'])
            ->notifiableToBoardMembers()
            ->whereDate('session_date', '>=', now()->toDateString())
            ->orderBy('session_date')
            ->orderBy('session_time')
            ->orderBy('id')
            ->first();
    }
}

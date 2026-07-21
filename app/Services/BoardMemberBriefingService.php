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
     *     session_today: bool
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
            ];
        }

        $nextSession = $this->nextSession();
        $myItems = $nextSession
            ? $this->dashboard->myCommitteeItemsOnSession($user, $nextSession)
            : collect();

        $deadlineCount = $this->dashboard->agendaStatsFor($user)['expiring_soon'] ?? 0;
        $stats = $this->dashboard->agendaStatsFor($user);

        return [
            'next_session' => $nextSession,
            'my_items_on_next_ob' => $myItems,
            'deadline_agendas' => $this->dashboard->expiringSoonAgendasFor($user, 12),
            'deadline_count' => $stats['expiring_soon'] ?? 0,
            'deadline_days' => $deadlineDays,
            'unread_notifications' => $user->unreadNotifications()->count(),
            'pending_count' => $stats['pending'] ?? 0,
            'session_today' => $nextSession?->session_date?->isToday() ?? false,
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

<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\Ordinance;
use App\Models\Resolution;
use App\Models\SeriesYear;
use App\Models\User;
use App\Services\BoardMemberBriefingService;
use App\Services\BoardMemberDashboardService;
use App\Services\MunicipalRequestService;
use App\Services\ResolutionRepository;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(
        ResolutionRepository $repository,
        BoardMemberDashboardService $boardDashboard,
        BoardMemberBriefingService $briefing,
        MunicipalRequestService $municipalRequests,
    ): View {
        $user = auth()->user();

        if ($user instanceof User && $user->isMunicipalViewer()) {
            $municipality = $municipalRequests->municipalityFor($user);

            return view('municipal.dashboard', [
                'user' => $user,
                'municipality' => $municipality,
                'unlinked' => $municipality === null,
                'stats' => $municipality
                    ? $municipalRequests->statsFor($user)
                    : ['pending' => 0, 'expiring_soon' => 0, 'due_soon' => 0, 'done' => 0, 'lapsed' => 0],
                'expiringSoonAgendas' => $municipality
                    ? $municipalRequests->expiringSoonRequestsFor($user)
                    : collect(),
                'expiringSoonDays' => $municipalRequests->expiringSoonDays(),
            ]);
        }

        if ($user instanceof User && $user->isBoardMember()) {
            $linked = $user->board_member_id !== null;
            $term = $boardDashboard->resolveTerm();

            return view('board-member.dashboard', [
                'user' => $user,
                'selectedTerm' => $term,
                'agendaStats' => $linked
                    ? $boardDashboard->agendaStatsFor($user)
                    : ['pending' => 0, 'expiring_soon' => 0, 'due_soon' => 0, 'done' => 0, 'lapsed' => 0],
                'expiringSoonDays' => $boardDashboard->expiringSoonDays(),
                'briefing' => $briefing->for($user),
                'unlinked' => ! $linked,
            ]);
        }

        $currentYear = (int) date('Y');
        $legacyCount = $repository->legacyCount();
        $newCount = $repository->newCount();
        $resolutionCount = $repository->totalCount();
        $ordinanceCount = Ordinance::query()->count();

        return view('dashboard', [
            'totalDocuments' => $resolutionCount + $ordinanceCount,
            'totalResolutions' => $resolutionCount,
            'totalOrdinances' => $ordinanceCount,
            'legacyCount' => $legacyCount,
            'newCount' => $newCount,
            'currentYearCount' => Resolution::query()->where('series', $currentYear)->count()
                + Ordinance::query()->where('series_year', $currentYear)->count(),
            'recentActivity' => $user->canAdmin()
                ? ActivityLog::query()->with('user')->latest('created_at')->limit(10)->get()
                : collect(),
            'categories' => Category::forSelect(),
            'departments' => Department::orderBy('description')->get(),
            'municipalities' => Municipality::orderBy('description')->get(),
            'seriesYears' => SeriesYear::orderByDesc('year')->pluck('year')
                ->merge(Ordinance::query()->distinct()->orderByDesc('series_year')->pluck('series_year'))
                ->unique()
                ->sortDesc()
                ->values(),
        ]);
    }
}

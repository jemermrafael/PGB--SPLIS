<?php

namespace App\Http\Controllers;

use App\Models\AgendaItem;
use App\Models\User;
use App\Services\MunicipalRequestService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MunicipalRequestController extends Controller
{
    public function index(Request $request, MunicipalRequestService $service): View
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isMunicipalViewer(), 403);

        $municipality = $service->municipalityFor($user);

        return view('municipal.requests.index', [
            'user' => $user,
            'municipality' => $municipality,
            'unlinked' => $municipality === null,
            'statuses' => config('agenda.statuses', []),
            'stats' => $municipality
                ? $service->statsFor($user)
                : ['pending' => 0, 'expiring_soon' => 0, 'due_soon' => 0, 'done' => 0, 'lapsed' => 0],
            'expiringSoonAgendas' => $municipality
                ? $service->expiringSoonRequestsFor($user)
                : collect(),
            'expiringSoonDays' => $service->expiringSoonDays(),
        ]);
    }

    public function show(Request $request, AgendaItem $agenda, MunicipalRequestService $service): View
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isMunicipalViewer(), 403);
        $this->authorize('view', $agenda);

        $agenda->load([
            'resolution',
            'ordinance',
            'appropriationOrdinance',
            'finalObPlacements.legislativeSession',
            'finalObPlacements.agendaItemVersion',
        ]);

        return view('municipal.requests.show', [
            'user' => $user,
            'agenda' => $agenda,
            'municipality' => $service->municipalityFor($user),
            'finalObPlacements' => $agenda->finalObPlacements,
        ]);
    }
}

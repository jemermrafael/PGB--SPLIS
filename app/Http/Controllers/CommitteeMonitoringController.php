<?php

namespace App\Http\Controllers;

use App\Models\Committee;
use App\Services\CommitteeMonitoringService;
use App\Support\CommitteeTermSelection;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommitteeMonitoringController extends Controller
{
    public function __construct(
        protected CommitteeMonitoringService $monitoringService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Committee::class);

        ['terms' => $terms, 'selectedTerm' => $selectedTerm] = CommitteeTermSelection::resolve(
            $request->integer('term') ?: null,
        );

        $filters = [
            'term' => $selectedTerm->id,
            'committee_id' => $request->integer('committee_id') ?: null,
            'status' => (string) $request->input('status', ''),
            'has_report' => (string) $request->input('has_report', ''),
            'date_from' => (string) $request->input('date_from', ''),
            'date_to' => (string) $request->input('date_to', ''),
            'q' => (string) $request->input('q', ''),
        ];

        return view('committee-monitoring.index', [
            'terms' => $terms,
            'selectedTerm' => $selectedTerm,
            'committees' => $this->monitoringService->committeeOptions($selectedTerm->id),
            'filters' => $filters,
            'stats' => $this->monitoringService->stats($filters),
            'items' => $this->monitoringService->paginate($filters, 25),
        ]);
    }
}


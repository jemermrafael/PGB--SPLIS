<?php

namespace App\Http\Controllers;

use App\Models\Committee;
use App\Services\CommitteeMonitoringService;
use App\Support\CommitteeTermSelection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommitteeMonitoringController extends Controller
{
    public function __construct(
        protected CommitteeMonitoringService $monitoringService,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('viewAny', Committee::class);

        $selectedTerm = CommitteeTermSelection::current();

        $baseFilters = [
            'term' => $selectedTerm->id,
            'committee_id' => $request->integer('committee_id') ?: null,
            'date_from' => (string) $request->input('date_from', ''),
            'date_to' => (string) $request->input('date_to', ''),
            'q' => (string) $request->input('q', ''),
        ];

        $view = $this->resolveView($request);
        $listFilters = $this->listFilters($baseFilters, $request, $view);
        $filters = array_merge($baseFilters, [
            'status' => (string) ($listFilters['status'] ?? ''),
            'has_report' => (string) ($listFilters['has_report'] ?? ''),
            'has_schedule' => (string) ($listFilters['has_schedule'] ?? ''),
            'view' => $view,
        ]);

        if ($request->expectsJson()) {
            return response()->json(array_merge(
                $this->monitoringService->searchPayload($listFilters, $baseFilters),
                [
                    'filters' => $filters,
                    'empty_message' => $this->emptyMessage($view),
                ],
            ));
        }

        return view('committee-monitoring.index', [
            'selectedTerm' => $selectedTerm,
            'committees' => $this->monitoringService->committeeOptions($selectedTerm->id),
            'filters' => $filters,
            'stats' => $this->monitoringService->stats($baseFilters),
        ]);
    }

    /**
     * @return 'referred'|'pending'|'scheduled'|'reports'|'completed'
     */
    protected function resolveView(Request $request): string
    {
        $status = (string) $request->input('status', '');
        $hasReport = (string) $request->input('has_report', '');

        if ($status === 'pending') {
            return 'pending';
        }

        if ($status === 'completed') {
            return 'completed';
        }

        if ($hasReport === 'yes') {
            return 'reports';
        }

        $view = (string) $request->input('view', 'referred');

        return in_array($view, ['referred', 'pending', 'scheduled', 'reports', 'completed'], true)
            ? $view
            : 'referred';
    }

    /**
     * @param  array<string, mixed>  $baseFilters
     * @return array<string, mixed>
     */
    protected function listFilters(array $baseFilters, Request $request, string $view): array
    {
        $filters = $baseFilters;
        $status = (string) $request->input('status', '');
        $hasReport = (string) $request->input('has_report', '');

        if ($status !== '') {
            $filters['status'] = $status;
        } elseif ($view === 'pending') {
            $filters['status'] = 'pending';
        } elseif ($view === 'completed') {
            $filters['status'] = 'completed';
        }

        if ($hasReport !== '') {
            $filters['has_report'] = $hasReport;
        } elseif ($view === 'reports') {
            $filters['has_report'] = 'yes';
        }

        if ($view === 'scheduled' && $status === '' && $hasReport === '') {
            $filters['has_schedule'] = 'yes';
        }

        return $filters;
    }

    protected function emptyMessage(string $view): string
    {
        return match ($view) {
            'pending' => 'No pending referred measures found for the selected filters.',
            'scheduled' => 'No scheduled committee meetings found for the selected filters.',
            'reports' => 'No referred measures with committee reports found for the selected filters.',
            'completed' => 'No completed referred measures found for the selected filters.',
            default => 'No referred measures found for the selected filters.',
        };
    }
}

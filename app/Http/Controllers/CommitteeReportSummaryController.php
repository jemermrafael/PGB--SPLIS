<?php

namespace App\Http\Controllers;

use App\Models\LegislativeSession;
use App\Services\CommitteeReportSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommitteeReportSummaryController extends Controller
{
    public function __construct(
        protected CommitteeReportSummaryService $summaries,
    ) {}

    public function maker(Request $request, LegislativeSession $legislativeSession): View
    {
        $this->authorize('update', $legislativeSession);

        $summary = $this->summaries->ensureForSession(
            $legislativeSession,
            $request->user()?->id,
        );

        return view('order-of-business.committee-report-summary.maker', [
            'session' => $legislativeSession,
            'summary' => $summary,
            'content' => $summary->normalizedContent(),
        ]);
    }

    public function update(Request $request, LegislativeSession $legislativeSession): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $legislativeSession);

        $summary = $this->summaries->ensureForSession(
            $legislativeSession,
            $request->user()?->id,
        );

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:500'],
            'title_html' => ['nullable', 'string', 'max:5000'],
            'report_date' => ['nullable', 'date'],
            'prepared_by.name' => ['nullable', 'string', 'max:255'],
            'prepared_by.title' => ['nullable', 'string', 'max:255'],
            'reviewed_by.name' => ['nullable', 'string', 'max:255'],
            'reviewed_by.title' => ['nullable', 'string', 'max:255'],
            'bodies' => ['nullable', 'array'],
            'bodies.*' => ['nullable', 'string', 'max:10000'],
            'bodies_html' => ['nullable', 'array'],
            'bodies_html.*' => ['nullable', 'string', 'max:20000'],
            'recommendations' => ['nullable', 'array'],
            'recommendations.*' => ['nullable', 'string', 'max:5000'],
            'recommendations_html' => ['nullable', 'array'],
            'recommendations_html.*' => ['nullable', 'string', 'max:10000'],
        ]);

        $summary = $this->summaries->update($summary, $validated);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Saved',
                'saved_at' => now()->toIso8601String(),
                'title' => $summary->title,
            ]);
        }

        return redirect()
            ->route('ob.sessions.committee-report-summary.maker', $legislativeSession)
            ->with('status', 'Summary of Committee Reports saved.');
    }

    public function sync(Request $request, LegislativeSession $legislativeSession): RedirectResponse
    {
        $this->authorize('update', $legislativeSession);

        $summary = $this->summaries->ensureForSession(
            $legislativeSession,
            $request->user()?->id,
        );

        $this->summaries->syncFromSessionOb($summary, preserveRecommendations: true);

        return redirect()
            ->route('ob.sessions.committee-report-summary.maker', $legislativeSession)
            ->with('status', 'Summary refreshed from Order of Business Committee Reports.');
    }

    public function print(Request $request, LegislativeSession $legislativeSession): View
    {
        $this->authorize('view', $legislativeSession);

        $summary = $this->summaries->ensureForSession(
            $legislativeSession,
            $request->user()?->id,
        );

        return view('order-of-business.committee-report-summary.print', [
            'session' => $legislativeSession,
            'summary' => $summary,
            'content' => $summary->normalizedContent(),
            'isEmbeddedPreview' => $request->boolean('embed'),
        ]);
    }
}

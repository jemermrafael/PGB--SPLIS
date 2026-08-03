<?php

namespace App\Http\Controllers;

use App\Models\LegislativeSession;
use App\Models\ScheduledCommitteeReferral;
use App\Services\CommitteeReferralScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ScheduledCommitteeReferralController extends Controller
{
    public function __construct(
        protected CommitteeReferralScheduleService $scheduleService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeEncode($request);

        $schedules = ScheduledCommitteeReferral::query()
            ->with(['legislativeSession', 'creator', 'deliveries'])
            ->orderByDesc('scheduled_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('scheduled-committee-referrals.index', [
            'schedules' => $schedules,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeEncode($request);

        $sessions = LegislativeSession::query()
            ->with('obDocument')
            ->whereHas('obDocument')
            ->orderByDesc('session_date')
            ->orderByDesc('id')
            ->limit(40)
            ->get();

        $selectedSessionId = $request->integer('legislative_session_id') ?: null;
        $selectedSession = $selectedSessionId
            ? $sessions->firstWhere('id', $selectedSessionId)
                ?? LegislativeSession::query()->with('obDocument.blocks.agendaItem')->find($selectedSessionId)
            : null;

        $preview = $selectedSession
            ? $this->scheduleService->previewForSession($selectedSession)
            : collect();

        return view('scheduled-committee-referrals.create', [
            'sessions' => $sessions,
            'selectedSession' => $selectedSession,
            'preview' => $preview,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeEncode($request);

        $data = $request->validate([
            'legislative_session_id' => ['required', 'integer', 'exists:legislative_sessions,id'],
            'scheduled_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $session = LegislativeSession::query()
            ->with('obDocument.blocks.agendaItem')
            ->findOrFail((int) $data['legislative_session_id']);

        try {
            $schedule = $this->scheduleService->schedule(
                $session,
                \Illuminate\Support\Carbon::parse($data['scheduled_at']),
                $request->user(),
                $data['notes'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'legislative_session_id' => $e->getMessage(),
            ]);
        }

        if ($schedule->scheduled_at->lte(now())) {
            $this->scheduleService->dispatch($schedule->fresh());
            $schedule->refresh();

            return redirect()
                ->route('scheduled-committee-referrals.index')
                ->with('status', 'Committee referrals sent to committee chairmen.');
        }

        return redirect()
            ->route('scheduled-committee-referrals.index')
            ->with('status', 'Committee referral scheduled for '.$schedule->scheduled_at->timezone(config('app.timezone'))->format('M j, Y g:i A').'.');
    }

    public function cancel(Request $request, ScheduledCommitteeReferral $scheduledCommitteeReferral): RedirectResponse
    {
        $this->authorizeEncode($request);

        if (! $scheduledCommitteeReferral->isPending()) {
            return redirect()
                ->route('scheduled-committee-referrals.index')
                ->with('status', 'Only pending schedules can be cancelled.');
        }

        $this->scheduleService->cancel($scheduledCommitteeReferral);

        return redirect()
            ->route('scheduled-committee-referrals.index')
            ->with('status', 'Scheduled committee referral cancelled.');
    }

    protected function authorizeEncode(Request $request): void
    {
        abort_unless($request->user()?->canEncode() ?? false, 403);
    }
}

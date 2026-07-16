<?php

namespace App\Http\Controllers;

use App\Models\AgendaItem;
use App\Models\AgendaItemVersion;
use App\Models\LegislativeSession;
use App\Services\AgendaExpirationNotifier;
use App\Services\AgendaIncomingPromoter;
use App\Services\AgendaItemRepository;
use App\Services\AgendaLifecycleService;
use App\Services\AgendaLinkService;
use App\Services\AgendaOutputLinker;
use App\Services\AgendaOutputPublisher;
use App\Services\AgendaVersionService;
use App\Services\BoardMemberNotifier;
use App\Services\MunicipalNotifier;
use App\Services\ObDocumentService;
use App\Support\ActivityLogger;
use App\Support\AgendaFieldOptions;
use App\Support\AgendaMeasureType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AgendaItemController extends Controller
{
    public function index(AgendaItemRepository $repository): View
    {
        $this->authorize('viewAny', AgendaItem::class);

        return view('agenda.index', [
            'statuses' => config('agenda.statuses', []),
            'senders' => AgendaFieldOptions::senders(),
            'committees' => AgendaFieldOptions::committees(),
            'outcomes' => AgendaFieldOptions::outcomes(),
            'stats' => $repository->stats(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', AgendaItem::class);

        return view('agenda.form', $this->formData(new AgendaItem));
    }

    public function store(
        Request $request,
        BoardMemberNotifier $notifier,
        AgendaOutputPublisher $publisher,
        AgendaVersionService $versions,
        AgendaLifecycleService $lifecycle,
    ): RedirectResponse {
        $this->authorize('create', AgendaItem::class);

        $agenda = AgendaItem::create(array_merge(
            $this->validated($request),
            ['created_by' => $request->user()->id],
        ));

        $versions->recordInitialVersion($agenda, $request->user()->id);

        ActivityLogger::log('agenda.created', $agenda, [
            'tracking_no' => $agenda->tracking_no,
            'title' => $agenda->title,
            'sender' => $agenda->sender,
        ]);

        $outputHandled = $this->afterAgendaSave($agenda, $request, $notifier, $publisher, false);

        $changedFields = array_values(array_filter([
            filled($agenda->committee_referred) ? 'committee_referred' : null,
            $agenda->is_urgent_request ? 'is_urgent_request' : null,
        ]));
        $lifecycle->handleAgendaSaved($agenda, $changedFields, $request->user()->id);

        return redirect()
            ->route('agenda.show', $agenda)
            ->with('status', $this->statusMessage('created', $agenda, false, $outputHandled));
    }

    public function show(Request $request, AgendaItem $agenda, AgendaOutputLinker $linker): View|RedirectResponse
    {
        if ($request->user()?->isMunicipalViewer()) {
            $this->authorize('view', $agenda);

            return redirect()->route('municipal.requests.show', $agenda);
        }

        $this->authorize('view', $agenda);

        $autoLinked = false;
        if ($request->user()?->can('update', $agenda)) {
            $linker->clearDanglingOutputLinks($agenda);
            $agenda->refresh();

            if ($agenda->needsOutputLink()) {
                $autoLinked = $linker->linkExistingIfPossible($agenda);
                $agenda->refresh();
            }
        }

        if ($autoLinked) {
            return redirect()
                ->route('agenda.show', $agenda)
                ->with('status', 'Provincial Output linked to '.$agenda->publishedTargetLabel().'.');
        }

        $agenda->load([
            'incomingDocument',
            'resolution',
            'ordinance',
            'appropriationOrdinance',
            'creator',
            'versions.creator',
            'finalObPlacements.legislativeSession',
            'finalObPlacements.agendaItemVersion',
            'finalObPlacements.obBlock',
            'obPlacements.legislativeSession',
            'obPlacements.agendaItemVersion',
            'obPlacements.obBlock',
            'obPlacements.obDocument',
            'activityLogs.user',
        ]);

        $obSessions = LegislativeSession::query()
            ->with('obDocument')
            ->whereHas('obDocument')
            ->orderByDesc('session_date')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        return view('agenda.show', [
            'agenda' => $agenda,
            'finalObPlacements' => $agenda->finalObPlacements,
            'obPlacements' => $agenda->obPlacements,
            'obSessions' => $obSessions,
            'splisActivityLogs' => $agenda->activityLogs,
            'obPlacementCount' => $agenda->activityLogs->where('action', 'agenda.added_to_ob')->count(),
            'outputLinkCandidates' => $agenda->needsOutputLink()
                ? $linker->candidateOptions($agenda)
                : collect(),
        ]);
    }

    public function edit(AgendaItem $agenda): View
    {
        $this->authorize('update', $agenda);

        $agenda->load(['resolution', 'ordinance', 'appropriationOrdinance']);

        return view('agenda.form', $this->formData($agenda));
    }

    public function update(
        Request $request,
        AgendaItem $agenda,
        BoardMemberNotifier $notifier,
        AgendaOutputPublisher $publisher,
        AgendaVersionService $versions,
        AgendaLifecycleService $lifecycle,
    ): RedirectResponse {
        $this->authorize('update', $agenda);

        $before = collect(AgendaVersionService::VERSIONED_FIELDS)
            ->mapWithKeys(fn (string $field) => [$field => $agenda->getAttribute($field)])
            ->all();

        $wasPublished = $agenda->isPublished();

        $agenda->update($this->validated($request));
        $changedFields = array_keys($agenda->getChanges());
        $agenda->refresh();

        $versions->recordVersionIfChanged($agenda, $before, $request->user()->id);

        $outputHandled = $this->afterAgendaSave($agenda, $request, $notifier, $publisher, $wasPublished);

        $lifecycle->handleAgendaSaved($agenda, $changedFields, $request->user()->id);

        return redirect()
            ->route('agenda.show', $agenda)
            ->with('status', $this->statusMessage('updated', $agenda, $wasPublished, $outputHandled));
    }

    protected function statusMessage(
        string $action,
        AgendaItem $agenda,
        bool $wasPublished = false,
        bool $outputHandled = false,
    ): string {
        $message = $action === 'created' ? 'Agenda item created.' : 'Agenda item updated.';

        if ($outputHandled && $agenda->isPublished()) {
            $message .= $wasPublished
                ? ' Output synced to '.$agenda->publishedTargetLabel().'.'
                : ' Published to '.$agenda->publishedTargetLabel().'.';
        }

        return $message;
    }

    protected function afterAgendaSave(
        AgendaItem $agenda,
        Request $request,
        BoardMemberNotifier $notifier,
        AgendaOutputPublisher $publisher,
        bool $wasPublishedBefore = false,
    ): bool {
        $agenda->refresh();

        if (filled($agenda->committee_referred)) {
            $notifier->notifyCommitteeReferral($agenda);
            app(MunicipalNotifier::class)->notifyCommitteeReferral($agenda);
        }

        app(AgendaExpirationNotifier::class)->syncForAgenda($agenda);

        if ($publisher->publishIfDone($agenda, $request->user()->id)) {
            $agenda->refresh();

            if (! $wasPublishedBefore && $agenda->isPublished()) {
                $notifier->notifyAgendaPublished($agenda);
                app(MunicipalNotifier::class)->notifyAgendaPublished($agenda);

                ActivityLogger::log('agenda.published', $agenda, [
                    'target' => $agenda->publishedTargetLabel(),
                    'output_no' => $agenda->reso_ord_ao_no,
                    'tracking_no' => $agenda->tracking_no,
                ]);
            }

            return true;
        }

        return false;
    }

    public function destroy(AgendaItem $agenda): RedirectResponse
    {
        $this->authorize('delete', $agenda);

        TrashActivity::record('agenda.trashed', $agenda);
        $agenda->delete();

        return redirect()
            ->route(auth()->user()?->isSuperadmin() ? 'admin.trash.index' : 'agenda.index', auth()->user()?->isSuperadmin() ? ['type' => 'agenda'] : [])
            ->with('status', 'Agenda item moved to trash.');
    }

    public function promote(Request $request, AgendaItem $agenda, AgendaIncomingPromoter $promoter): RedirectResponse
    {
        $this->authorize('promote', $agenda);

        try {
            $incoming = $promoter->promote($agenda, $request->user()->id);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['promote' => $e->getMessage()]);
        }

        return redirect()
            ->route('incoming.show', $incoming)
            ->with('status', 'Incoming record created from agenda item.');
    }

    public function unlinkIncoming(Request $request, AgendaItem $agenda, AgendaLinkService $links): RedirectResponse
    {
        $this->authorize('unlinkIncoming', $agenda);

        try {
            $links->unlinkIncoming($agenda);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['unlink' => $e->getMessage()]);
        }

        return redirect()
            ->route('agenda.show', $agenda)
            ->with('status', 'Incoming link removed. You can create a new incoming record from this agenda item.');
    }

    public function unlinkResolution(Request $request, AgendaItem $agenda, AgendaLinkService $links): RedirectResponse
    {
        $this->authorize('unlinkResolution', $agenda);

        try {
            $links->unlinkResolution($agenda);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['unlink' => $e->getMessage()]);
        }

        return redirect()
            ->route('agenda.show', $agenda)
            ->with('status', 'Resolution link removed from this agenda item.');
    }

    public function linkOutput(Request $request, AgendaItem $agenda, AgendaOutputLinker $linker): RedirectResponse
    {
        $this->authorize('linkOutput', $agenda);

        $validated = $request->validate([
            'output_type' => ['required', 'string', Rule::in(AgendaMeasureType::options())],
            'output_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $linker->linkManual($agenda, $validated['output_type'], (int) $validated['output_id']);
        } catch (\Throwable $e) {
            return back()->withErrors(['link_output' => $e->getMessage()]);
        }

        $agenda->refresh();

        return redirect()
            ->route('agenda.show', $agenda)
            ->with('status', 'Provincial Output linked to '.$agenda->publishedTargetLabel().'.');
    }

    public function addToOrderOfBusiness(Request $request, AgendaItem $agenda, ObDocumentService $documentService): RedirectResponse
    {
        $this->authorize('addToOrderOfBusiness', $agenda);

        $validated = $request->validate([
            'legislative_session_id' => ['required', 'integer', 'exists:legislative_sessions,id'],
            'agenda_section' => ['nullable', 'string', 'in:'.implode(',', array_keys(config('order_of_business.agenda_sections', [])))],
        ]);

        $session = LegislativeSession::query()
            ->with('obDocument')
            ->findOrFail($validated['legislative_session_id']);

        abort_unless($session->obDocument, 404, 'This session has no Order of Business document.');

        $documentService->addAgendaItems(
            $session->obDocument,
            [$agenda->id],
            null,
            $validated['agenda_section'] ?? 'unassigned_regular',
            null,
            $request->user()->id,
        );

        return redirect()
            ->route('ob.document.maker', $session)
            ->with('status', 'Agenda '.$agenda->displayLabel().' added to '.$session->displayTitle().'.');
    }

    public function removeFromOrderOfBusiness(
        Request $request,
        AgendaItem $agenda,
        ObDocumentService $documentService,
    ): RedirectResponse {
        $this->authorize('removeFromOrderOfBusiness', $agenda);

        $validated = $request->validate([
            'legislative_session_id' => ['required', 'integer', 'exists:legislative_sessions,id'],
        ]);

        $session = LegislativeSession::query()
            ->with('obDocument')
            ->findOrFail($validated['legislative_session_id']);

        abort_unless($session->obDocument, 404, 'This session has no Order of Business document.');

        $hasPlacement = $agenda->obPlacements()
            ->where('legislative_session_id', $session->id)
            ->exists();

        abort_unless($hasPlacement, 404, 'This agenda item is not on that Order of Business.');

        $documentService->removeAgendaFromDocument(
            $session->obDocument,
            $agenda,
            $request->user()->id,
            'manual',
        );

        $agenda->forceFill(['ob_manual_override_at' => now()])->saveQuietly();

        return redirect()
            ->route('agenda.show', $agenda)
            ->with('status', 'Agenda '.$agenda->displayLabel().' removed from '.$session->displayTitle().'.');
    }

    public function destroyVersion(
        AgendaItem $agenda,
        AgendaItemVersion $version,
        AgendaVersionService $versions,
    ): RedirectResponse {
        abort_unless($version->agenda_item_id === $agenda->id, 404);

        $this->authorize('delete', $version);

        try {
            $versions->deleteVersion($version);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['version' => $e->getMessage()]);
        }

        return redirect()
            ->route('agenda.show', $agenda)
            ->with('status', 'Version v'.$version->version_no.' deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function formData(AgendaItem $agenda): array
    {
        return [
            'agenda' => $agenda,
            'senders' => AgendaFieldOptions::senders(),
            'committees' => AgendaFieldOptions::committees(),
            'outcomes' => AgendaFieldOptions::outcomes(),
            'statuses' => config('agenda.statuses', []),
            'prescribedDays' => config('agenda.prescribed_days', []),
            'measureTypes' => config('agenda.measure_types', []),
            'deadlinePreviewUrl' => route('agenda.preview-deadline'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'tracking_no' => ['nullable', 'string', 'max:20'],
            'request_pdf_url' => ['nullable', 'string', 'max:500'],
            'date_received' => ['nullable', 'date'],
            'time_received' => ['nullable', 'date_format:H:i'],
            'prescribed_days' => ['nullable', 'integer', 'in:0,30,60,90'],
            'status' => ['nullable', 'string', 'in:no_due_date,pending,done,lapsed'],
            'sender' => ['nullable', 'string', 'max:150'],
            'title' => ['nullable', 'string', 'max:5000'],
            'is_urgent_request' => ['nullable', 'boolean'],
            'committee_referred' => ['nullable', 'string', 'max:200'],
            'date_of_referral' => ['nullable', 'date'],
            'date_of_committee_meeting' => ['nullable', 'date'],
            'committee_meeting_minutes' => ['nullable', 'string', 'max:200'],
            'outcome' => ['nullable', 'string', 'max:80'],
            'committee_report_url' => ['nullable', 'string', 'max:500'],
            'date_passed' => ['nullable', 'date'],
            'date_signed_by_gov' => ['nullable', 'date'],
            'reso_ord_ao_no' => ['nullable', 'string', 'max:50'],
            'reso_ord_ao_series' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'reso_ord_ao_type' => [
                'nullable',
                'string',
                Rule::in(AgendaMeasureType::options()),
            ],
            'reso_ord_ao_url' => ['nullable', 'string', 'max:500'],
            'resolution_title' => ['nullable', 'string', 'max:5000'],
            'journal_url' => ['nullable', 'string', 'max:500'],
            'minutes_url' => ['nullable', 'string', 'max:500'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ]);

        if (empty($data['status'])) {
            $data['status'] = AgendaItem::STATUS_PENDING;
        }

        $data['is_urgent_request'] = $request->boolean('is_urgent_request');

        return $data;
    }
}

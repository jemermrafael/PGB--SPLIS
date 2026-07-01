<?php

namespace App\Http\Controllers;

use App\Models\AgendaItem;
use App\Models\LegislativeSession;
use App\Services\AgendaIncomingPromoter;
use App\Services\AgendaLinkService;
use App\Services\AgendaItemRepository;
use App\Services\ObDocumentService;
use App\Support\AgendaFieldOptions;
use App\Support\AgendaMeasureType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgendaItemController extends Controller
{
    public function index(AgendaItemRepository $repository): View
    {
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

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', AgendaItem::class);

        $agenda = AgendaItem::create(array_merge(
            $this->validated($request),
            ['created_by' => $request->user()->id],
        ));

        return redirect()
            ->route('agenda.show', $agenda)
            ->with('status', 'Agenda item created.');
    }

    public function show(AgendaItem $agenda): View
    {
        $agenda->load([
            'incomingDocument',
            'resolution',
            'creator',
            'obBlocks.obDocument.legislativeSession',
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
            'obSessions' => $obSessions,
        ]);
    }

    public function edit(AgendaItem $agenda): View
    {
        $this->authorize('update', $agenda);

        return view('agenda.form', $this->formData($agenda));
    }

    public function update(Request $request, AgendaItem $agenda): RedirectResponse
    {
        $this->authorize('update', $agenda);

        $agenda->update($this->validated($request));

        return redirect()
            ->route('agenda.show', $agenda)
            ->with('status', 'Agenda item updated.');
    }

    public function destroy(AgendaItem $agenda): RedirectResponse
    {
        $this->authorize('delete', $agenda);

        $agenda->delete();

        return redirect()
            ->route('agenda.index')
            ->with('status', 'Agenda item deleted.');
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
        );

        return redirect()
            ->route('ob.document.maker', $session)
            ->with('status', 'Agenda '.$agenda->displayLabel().' added to '.$session->displayTitle().'.');
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
            'reso_ord_ao_type' => ['nullable', 'string', 'in:'.implode(',', AgendaMeasureType::options())],
            'reso_ord_ao_url' => ['nullable', 'string', 'max:500'],
            'resolution_title' => ['nullable', 'string', 'max:5000'],
            'journal_url' => ['nullable', 'string', 'max:500'],
            'minutes_url' => ['nullable', 'string', 'max:500'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ]);

        if (empty($data['status'])) {
            $data['status'] = AgendaItem::STATUS_PENDING;
        }

        return $data;
    }
}

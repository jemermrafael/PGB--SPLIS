<?php

namespace App\Http\Controllers;

use App\Support\TrashActivity;
use App\Models\LegislativeSession;
use App\Models\ObDocument;
use App\Services\AgendaLifecycleService;
use App\Services\BoardMemberNotifier;
use App\Services\ObDocumentTemplateService;
use App\Services\ObSectionThreeSyncService;
use App\Services\SessionCommitteeReportFileService;
use App\Services\SessionPdfService;
use App\Support\SessionPdfSlot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LegislativeSessionController extends Controller
{
    public function __construct(
        protected SessionPdfService $sessionPdfService,
        protected SessionCommitteeReportFileService $committeeReportFileService,
    ) {}
    public function index(): View
    {
        $this->authorize('viewAny', LegislativeSession::class);

        $query = LegislativeSession::query()
            ->with(['obDocument' => fn ($query) => $query->withCount('blocks'), 'creator'])
            ->orderByDesc('session_date')
            ->orderByDesc('id');

        if (auth()->user()?->isBoardMember()) {
            $query->visibleToBoardMembers();
        }

        $sessions = $query->paginate(20);

        return view('order-of-business.sessions.index', [
            'sessions' => $sessions,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', LegislativeSession::class);

        return view('order-of-business.sessions.form', $this->formData(new LegislativeSession));
    }

    public function store(Request $request, ObDocumentTemplateService $templateService, BoardMemberNotifier $notifier, AgendaLifecycleService $lifecycle, ObSectionThreeSyncService $sectionThreeSync): RedirectResponse
    {
        $this->authorize('create', LegislativeSession::class);

        $session = LegislativeSession::create(array_merge(
            $this->validated($request),
            ['created_by' => $request->user()->id],
        ));

        $document = ObDocument::create([
            'legislative_session_id' => $session->id,
            'title' => 'Order of Business — '.$session->session_date->format('F j, Y'),
            'status' => 'draft',
            'created_by' => $request->user()->id,
        ]);

        $templateService->seedDefaultBlocks($document);

        $sectionThreeSync->syncForSession($session->fresh(['priorSession', 'obDocument.blocks']), force: true);

        $notifier->notifySessionCreated($session);
        $notifier->notifyObDocumentCreated($session, $document);

        $lifecycle->syncNewSession($session, $request->user()->id);

        return redirect()
            ->route('ob.document.maker', $session)
            ->with('status', 'Session and Order of Business document created. Add agenda items in the sections below.');
    }

    public function show(LegislativeSession $legislativeSession): View
    {
        $this->authorize('view', $legislativeSession);

        $legislativeSession->load([
            'obDocument.blocks.agendaItem',
            'priorSession',
            'creator',
            'committeeReportFiles',
        ]);

        return view('order-of-business.sessions.show', [
            'session' => $legislativeSession,
        ]);
    }

    public function edit(LegislativeSession $legislativeSession): View
    {
        $this->authorize('update', $legislativeSession);

        $legislativeSession->load('committeeReportFiles');

        return view('order-of-business.sessions.form', $this->formData($legislativeSession));
    }

    public function update(Request $request, LegislativeSession $legislativeSession, ObSectionThreeSyncService $sectionThreeSync): RedirectResponse
    {
        $this->authorize('update', $legislativeSession);

        $priorSessionChanged = (int) $request->input('prior_session_id') !== (int) $legislativeSession->prior_session_id;

        $validated = $this->validated($request);

        foreach (SessionPdfSlot::mirrorable() as $slot) {
            unset($validated[SessionPdfSlot::config($slot)['upload']]);
        }
        unset($validated['committee_report_files']);

        $legislativeSession->update($validated);

        $this->storeUploadedPdfs($request, $legislativeSession);
        $this->storeUploadedCommitteeReportFiles($request, $legislativeSession, $request->user()?->id);

        if ($priorSessionChanged) {
            $sectionThreeSync->syncForSession($legislativeSession->fresh(['priorSession', 'obDocument.blocks']), force: true);
        }

        if ($legislativeSession->obDocument) {
            $legislativeSession->obDocument->update([
                'title' => 'Order of Business — '.$legislativeSession->session_date->format('F j, Y'),
            ]);
        }

        return redirect()
            ->route('ob.sessions.show', $legislativeSession)
            ->with('status', 'Session updated.');
    }

    public function destroy(LegislativeSession $legislativeSession): RedirectResponse
    {
        $this->authorize('delete', $legislativeSession);

        TrashActivity::record('legislative_session.trashed', $legislativeSession);
        $legislativeSession->delete();

        return redirect()
            ->route(auth()->user()?->isSuperadmin() ? 'admin.trash.index' : 'ob.sessions.index', auth()->user()?->isSuperadmin() ? ['type' => 'sessions'] : [])
            ->with('status', 'Order of Business session moved to trash.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function formData(LegislativeSession $session): array
    {
        $priorSessions = LegislativeSession::query()
            ->when($session->exists, fn ($query) => $query->whereKeyNot($session->id))
            ->orderByDesc('session_date')
            ->limit(50)
            ->get();

        return [
            'session' => $session,
            'sessionKinds' => config('order_of_business.session_kinds', []),
            'sessionStatuses' => config('order_of_business.session_statuses', []),
            'priorSessions' => $priorSessions,
            'sessionPdfLinks' => config('order_of_business.session_pdf_links', []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        $rules = [
            'session_date' => ['required', 'date'],
            'session_time' => ['nullable', 'date_format:H:i'],
            'session_number' => ['nullable', 'string', 'max:120'],
            'session_kind' => ['required', 'string', 'in:'.implode(',', array_keys(config('order_of_business.session_kinds', [])))],
            'venue' => ['nullable', 'string', 'max:200'],
            'prior_session_id' => ['nullable', 'integer', 'exists:legislative_sessions,id'],
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(config('order_of_business.session_statuses', [])))],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];

        foreach (array_keys(config('order_of_business.session_pdf_links', [])) as $field) {
            if (SessionPdfSlot::isValid($field) && SessionPdfSlot::isMakerOnly($field)) {
                continue;
            }

            $rules[$field] = ['nullable', 'string', 'max:500'];
        }

        foreach (SessionPdfSlot::mirrorable() as $slot) {
            $upload = SessionPdfSlot::config($slot)['upload'];
            $rules[$upload] = ['nullable', 'file', 'mimes:'.SessionPdfSlot::uploadMimes($slot), 'max:51200'];
        }

        $rules['committee_report_files'] = ['nullable', 'array'];
        $rules['committee_report_files.*'] = ['file', 'mimes:pdf,jpg,jpeg,png,gif,webp', 'max:51200'];

        return $request->validate($rules);
    }

    protected function storeUploadedPdfs(Request $request, LegislativeSession $session): void
    {
        foreach (SessionPdfSlot::mirrorable() as $slot) {
            $field = SessionPdfSlot::config($slot)['upload'];

            if (! $request->hasFile($field)) {
                continue;
            }

            $path = $this->sessionPdfService->store(
                $request->file($field),
                $session,
                $slot,
            );

            $pathColumn = SessionPdfSlot::config($slot)['path'];
            $session->update([$pathColumn => $path]);
        }
    }

    protected function storeUploadedCommitteeReportFiles(Request $request, LegislativeSession $session, ?int $userId): void
    {
        if (! $request->hasFile('committee_report_files')) {
            return;
        }

        foreach ($request->file('committee_report_files') as $uploadedFile) {
            if ($uploadedFile === null) {
                continue;
            }

            $this->committeeReportFileService->store($uploadedFile, $session, $userId);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\LegislativeSession;
use App\Models\ObDocument;
use App\Services\ObDocumentTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LegislativeSessionController extends Controller
{
    public function index(): View
    {
        $sessions = LegislativeSession::query()
            ->with(['obDocument' => fn ($query) => $query->withCount('blocks'), 'creator'])
            ->orderByDesc('session_date')
            ->orderByDesc('id')
            ->paginate(20);

        return view('order-of-business.sessions.index', [
            'sessions' => $sessions,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', LegislativeSession::class);

        return view('order-of-business.sessions.form', $this->formData(new LegislativeSession));
    }

    public function store(Request $request, ObDocumentTemplateService $templateService): RedirectResponse
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

        return redirect()
            ->route('ob.document.maker', $session)
            ->with('status', 'Session and Order of Business document created. Add agenda items in the sections below.');
    }

    public function show(LegislativeSession $legislativeSession): View
    {
        $legislativeSession->load([
            'obDocument.blocks.agendaItem',
            'priorSession',
            'creator',
        ]);

        return view('order-of-business.sessions.show', [
            'session' => $legislativeSession,
        ]);
    }

    public function edit(LegislativeSession $legislativeSession): View
    {
        $this->authorize('update', $legislativeSession);

        return view('order-of-business.sessions.form', $this->formData($legislativeSession));
    }

    public function update(Request $request, LegislativeSession $legislativeSession): RedirectResponse
    {
        $this->authorize('update', $legislativeSession);

        $legislativeSession->update($this->validated($request));

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

        $legislativeSession->delete();

        return redirect()
            ->route('ob.sessions.index')
            ->with('status', 'Order of Business session deleted.');
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        return $request->validate([
            'session_date' => ['required', 'date'],
            'session_time' => ['nullable', 'date_format:H:i'],
            'session_number' => ['nullable', 'string', 'max:120'],
            'session_kind' => ['required', 'string', 'in:'.implode(',', array_keys(config('order_of_business.session_kinds', [])))],
            'venue' => ['nullable', 'string', 'max:200'],
            'prior_session_id' => ['nullable', 'integer', 'exists:legislative_sessions,id'],
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(config('order_of_business.session_statuses', [])))],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}

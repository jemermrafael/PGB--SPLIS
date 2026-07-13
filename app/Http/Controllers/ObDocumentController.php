<?php

namespace App\Http\Controllers;

use App\Enums\ObBlockType;
use App\Models\LegislativeSession;
use App\Models\ObBlock;
use App\Models\ObDocument;
use App\Services\AgendaLifecycleService;
use App\Services\ObAgendaPoolService;
use App\Services\ObDocumentService;
use App\Services\ObPrintRenderer;
use App\Services\ObSectionThreeSyncService;
use App\Support\CommitteeOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ObDocumentController extends Controller
{
    public function maker(LegislativeSession $legislativeSession, ObDocumentService $service, ObSectionThreeSyncService $sectionThreeSync): View
    {
        $document = $this->documentFor($legislativeSession);
        $this->authorize('update', $document);

        $legislativeSession->load('priorSession');
        $sectionThreeSync->syncForSession($legislativeSession);
        $document->refresh();

        $session = $legislativeSession;

        return view('order-of-business.document.maker', [
            'session' => $session,
            'document' => $document,
            'canEdit' => auth()->user()?->can('update', $document) ?? false,
            'makerConfig' => [
                'urls' => [
                    'updateDocument' => route('ob.document.update', $session),
                    'storeBlock' => route('ob.document.blocks.store', $session),
                    'reorder' => route('ob.document.blocks.reorder', $session),
                    'fromAgenda' => route('ob.document.blocks.from-agenda', $session),
                    'syncAgendas' => route('ob.document.sync-agendas', $session),
                    'agendaPool' => route('ob.document.agenda-pool', $session),
                    'updateBlock' => route('ob.document.blocks.update', [$session, '__BLOCK__']),
                    'deleteBlock' => route('ob.document.blocks.destroy', [$session, '__BLOCK__']),
                    'moveSection' => route('ob.document.blocks.move-section', [$session, '__BLOCK__']),
                    'print' => route('ob.document.print', $session),
                    'session' => route('ob.sessions.show', $session),
                ],
                'initial' => [
                    'document' => $service->documentPayload($document),
                    'blocks' => $service->blocksPayload($document),
                ],
                'blockTypes' => collect(ObBlockType::makerTypes())->map(fn (ObBlockType $type) => [
                    'value' => $type->value,
                    'label' => $type->label(),
                ])->values()->all(),
                'agendaSections' => collect(config('order_of_business.agenda_sections', []))
                    ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                    ->values()
                    ->all(),
                'documentStatuses' => collect(config('order_of_business.document_statuses', []))
                    ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                    ->values()
                    ->all(),
                'committees' => CommitteeOptions::forSelect(),
                'sectionThree' => $this->sectionThreeConfig($session),
            ],
        ]);
    }

    public function print(LegislativeSession $legislativeSession, ObPrintRenderer $renderer): View
    {
        $document = $this->documentFor($legislativeSession);
        $this->authorize('view', $document);

        $legislativeSession->load(['priorSession', 'obDocument.blocks']);

        $blocks = $document->blocks()->with('agendaItem')->orderBy('sort_order')->get();

        return view('order-of-business.document.print', [
            'session' => $legislativeSession,
            'document' => $document,
            'segments' => $renderer->segments($blocks, $legislativeSession),
        ]);
    }

    public function update(Request $request, LegislativeSession $legislativeSession, ObDocumentService $service): JsonResponse
    {
        $document = $this->documentFor($legislativeSession);
        $this->authorize('update', $document);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:'.implode(',', array_keys(config('order_of_business.document_statuses', [])))],
        ]);

        $document = $service->updateDocument($document, $data);

        return response()->json([
            'document' => $service->documentPayload($document),
        ]);
    }

    public function storeBlock(Request $request, LegislativeSession $legislativeSession, ObDocumentService $service): JsonResponse
    {
        $document = $this->documentFor($legislativeSession);
        $this->authorize('update', $document);

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', array_column(ObBlockType::cases(), 'value'))],
            'content' => ['nullable', 'array'],
            'after_block_id' => ['nullable', 'integer', 'exists:ob_blocks,id'],
        ]);

        $type = ObBlockType::from($validated['type']);
        $block = $service->addBlock(
            $document,
            $type,
            $validated['content'] ?? null,
            $validated['after_block_id'] ?? null,
        );

        return response()->json([
            'block' => $service->blockPayload($block),
            'blocks' => $service->blocksPayload($document->fresh()),
            'document' => $service->documentPayload($document->fresh()),
        ], 201);
    }

    public function updateBlock(
        Request $request,
        LegislativeSession $legislativeSession,
        ObBlock $block,
        ObDocumentService $service,
    ): JsonResponse {
        $document = $this->documentFor($legislativeSession);
        $this->authorize('update', $document);
        $this->ensureBlockBelongsToDocument($block, $document);

        $validated = $request->validate([
            'content' => ['required', 'array'],
        ]);

        $block = $service->updateBlock($block, $validated['content']);

        return response()->json([
            'block' => $service->blockPayload($block),
            'blocks' => $service->blocksPayload($document->fresh()),
        ]);
    }

    public function destroyBlock(
        LegislativeSession $legislativeSession,
        ObBlock $block,
        ObDocumentService $service,
    ): JsonResponse {
        $document = $this->documentFor($legislativeSession);
        $this->authorize('update', $document);
        $this->ensureBlockBelongsToDocument($block, $document);

        $service->deleteBlock($block, auth()->id());

        return response()->json([
            'blocks' => $service->blocksPayload($document->fresh()),
            'document' => $service->documentPayload($document->fresh()),
        ]);
    }

    public function moveBlockToSection(
        Request $request,
        LegislativeSession $legislativeSession,
        ObBlock $block,
        ObDocumentService $service,
    ): JsonResponse {
        $document = $this->documentFor($legislativeSession);
        $this->authorize('update', $document);
        $this->ensureBlockBelongsToDocument($block, $document);

        $validated = $request->validate([
            'section' => ['required', 'string', 'in:'.implode(',', array_keys(config('order_of_business.agenda_sections', [])))],
        ]);

        $service->moveAgendaBlockToSection($block, $validated['section'], $request->user()->id);

        return response()->json([
            'blocks' => $service->blocksPayload($document->fresh()),
            'document' => $service->documentPayload($document->fresh()),
        ]);
    }

    public function reorderBlocks(
        Request $request,
        LegislativeSession $legislativeSession,
        ObDocumentService $service,
    ): JsonResponse {
        $document = $this->documentFor($legislativeSession);
        $this->authorize('update', $document);

        $validated = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer', 'exists:ob_blocks,id'],
        ]);

        $service->reorderBlocks($document, $validated['order']);

        return response()->json([
            'blocks' => $service->blocksPayload($document->fresh()),
        ]);
    }

    public function addFromAgenda(
        Request $request,
        LegislativeSession $legislativeSession,
        ObDocumentService $service,
    ): JsonResponse {
        $document = $this->documentFor($legislativeSession);
        $this->authorize('update', $document);

        $validated = $request->validate([
            'agenda_item_ids' => ['required', 'array', 'min:1'],
            'agenda_item_ids.*' => ['integer', 'exists:agenda_items,id'],
            'after_block_id' => ['nullable', 'integer', 'exists:ob_blocks,id'],
            'section' => ['nullable', 'string', 'in:'.implode(',', array_keys(config('order_of_business.agenda_sections', [])))],
            'committee_id' => ['nullable', 'integer', 'exists:committees,id'],
        ]);

        $created = $service->addAgendaItems(
            $document,
            $validated['agenda_item_ids'],
            $validated['after_block_id'] ?? null,
            $validated['section'] ?? 'unassigned_regular',
            $validated['committee_id'] ?? null,
            $request->user()->id,
        );

        return response()->json([
            'blocks' => collect($created)->map(fn (ObBlock $block) => $service->blockPayload($block))->values(),
            'all_blocks' => $service->blocksPayload($document->fresh()),
            'document' => $service->documentPayload($document->fresh()),
        ], 201);
    }

    public function syncAgendas(
        LegislativeSession $legislativeSession,
        ObDocumentService $service,
        AgendaLifecycleService $lifecycle,
    ): JsonResponse {
        $document = $this->documentFor($legislativeSession);
        $this->authorize('update', $document);

        if (! in_array($legislativeSession->status, ['draft', 'scheduled'], true)) {
            return response()->json([
                'message' => 'Auto-place is only available for draft or scheduled sessions.',
            ], 422);
        }

        $result = $lifecycle->syncNewSession(
            $legislativeSession->fresh(['obDocument', 'priorSession']),
            auth()->id(),
            clearManualOverrides: false,
        );

        $document = $document->fresh();

        return response()->json([
            'added' => $result['added'],
            'relocated' => $result['relocated'],
            'blocks' => $service->blocksPayload($document),
            'document' => $service->documentPayload($document),
        ]);
    }

    protected function documentFor(LegislativeSession $session): ObDocument
    {
        $document = $session->obDocument;

        abort_unless($document, 404, 'No Order of Business document for this session.');

        return $document;
    }

    protected function ensureBlockBelongsToDocument(ObBlock $block, ObDocument $document): void
    {
        abort_unless($block->ob_document_id === $document->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    protected function sectionThreeConfig(LegislativeSession $session): array
    {
        $prior = $session->priorSession;

        return [
            'prior_session_title' => $prior?->displayTitle(),
            'journal_url' => filled($prior?->pdf_final_journal)
                ? $prior->pdf_final_journal
                : $prior?->pdf_draft_journal,
            'minutes_url' => filled($prior?->pdf_final_minutes)
                ? $prior->pdf_final_minutes
                : $prior?->pdf_draft_minutes,
        ];
    }
}

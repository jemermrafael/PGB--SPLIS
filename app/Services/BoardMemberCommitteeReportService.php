<?php

namespace App\Services;

use App\Enums\ObBlockType;
use App\Models\AgendaItem;
use App\Models\AgendaObPlacement;
use App\Models\BoardMemberCommitteeReport;
use App\Models\LegislativeSession;
use App\Models\LegislativeSessionCommitteeReportFile;
use App\Models\ObBlock;
use App\Models\User;
use App\Support\ObAgendaSnapshot;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BoardMemberCommitteeReportService
{
    public function __construct(
        protected BoardMemberDashboardService $dashboard,
        protected SessionCommitteeReportFileService $sessionCommitteeReports,
        protected AgendaLifecycleService $lifecycle,
        protected ObDocumentService $documentService,
    ) {}

    /**
     * @param  list<int|string>  $agendaItemIds
     */
    public function store(
        User $user,
        UploadedFile $pdf,
        ?string $title,
        array $agendaItemIds,
        ?int $onBehalfOfBoardMemberId = null,
    ): BoardMemberCommitteeReport {
        $boardMemberId = $this->resolveTargetBoardMemberId($user, $onBehalfOfBoardMemberId);

        $allowedIds = $this->resolveAllowedAgendaIds($boardMemberId, $agendaItemIds);

        $directory = "board-member-committee-reports/{$boardMemberId}";
        $storedPath = $pdf->store($directory, 'local');
        $contents = (string) Storage::disk('local')->get($storedPath);
        $reportTitle = trim((string) $title) ?: null;
        $fileSize = $pdf->getSize() ?: strlen($contents);

        return DB::transaction(function () use (
            $user,
            $boardMemberId,
            $storedPath,
            $contents,
            $reportTitle,
            $allowedIds,
            $fileSize,
        ): BoardMemberCommitteeReport {
            $report = BoardMemberCommitteeReport::query()->create([
                'board_member_id' => $boardMemberId,
                'title' => $reportTitle,
                'pdf_path' => $storedPath,
                'original_filename' => null,
                'previous_ob_placements' => [],
                'submitted_by' => $user->id,
                'submitted_at' => now(),
            ]);

            $this->syncAgendaAttachments(
                $report,
                $user,
                $allowedIds->all(),
                $contents,
                $fileSize,
                clearPrevious: false,
            );

            return $report->fresh(['agendaItems']);
        });
    }

    /**
     * @param  list<int|string>  $agendaItemIds
     */
    public function update(
        User $user,
        BoardMemberCommitteeReport $report,
        ?UploadedFile $pdf,
        ?string $title,
        array $agendaItemIds,
    ): BoardMemberCommitteeReport {
        $this->assertCanMutate($user, $report);

        $allowedIds = $this->resolveAllowedAgendaIds((int) $report->board_member_id, $agendaItemIds, $report);
        $reportTitle = trim((string) $title) ?: null;

        return DB::transaction(function () use (
            $user,
            $report,
            $pdf,
            $reportTitle,
            $allowedIds,
        ): BoardMemberCommitteeReport {
            $report->forceFill([
                'title' => $reportTitle,
            ])->save();

            if ($pdf !== null) {
                $directory = "board-member-committee-reports/{$report->board_member_id}";
                $newPath = $pdf->store($directory, 'local');
                $contents = (string) Storage::disk('local')->get($newPath);
                $fileSize = $pdf->getSize() ?: strlen($contents);

                if (filled($report->pdf_path) && Storage::disk('local')->exists($report->pdf_path)) {
                    Storage::disk('local')->delete($report->pdf_path);
                }

                $report->forceFill(['pdf_path' => $newPath])->save();
            } else {
                abort_unless(Storage::disk('local')->exists($report->pdf_path), 404);
                $contents = (string) Storage::disk('local')->get($report->pdf_path);
                $fileSize = strlen($contents);
            }

            $this->syncAgendaAttachments(
                $report,
                $user,
                $allowedIds->all(),
                $contents,
                $fileSize,
                clearPrevious: true,
            );

            return $report->fresh(['agendaItems']);
        });
    }

    public function delete(User $user, BoardMemberCommitteeReport $report): void
    {
        $this->assertCanMutate($user, $report);

        DB::transaction(function () use ($user, $report): void {
            $agendaIds = $report->agendaItems()->pluck('agenda_items.id')->map(fn ($id) => (int) $id)->all();

            $this->restorePreviousObPlacements($report, $user->id);
            $this->clearAgendaReportFields($agendaIds, deleteSharedFile: false);
            $this->deleteSessionFilesForReport($report);

            if (filled($report->pdf_path) && Storage::disk('local')->exists($report->pdf_path)) {
                Storage::disk('local')->delete($report->pdf_path);
            }

            $report->agendaItems()->detach();
            $report->delete();
        });
    }

    protected function resolveTargetBoardMemberId(User $user, ?int $onBehalfOfBoardMemberId): int
    {
        if ($user->isBoardMember()) {
            $boardMemberId = (int) $user->board_member_id;
            abort_if($boardMemberId <= 0, 403);

            return $boardMemberId;
        }

        abort_unless($user->canEncode(), 403);
        $boardMemberId = (int) ($onBehalfOfBoardMemberId ?? 0);
        abort_if($boardMemberId <= 0, 422, 'Select a Board Member chair for this report.');

        return $boardMemberId;
    }

    protected function assertCanMutate(User $user, BoardMemberCommitteeReport $report): void
    {
        if ($user->isBoardMember()
            && $user->board_member_id !== null
            && (int) $report->board_member_id === (int) $user->board_member_id) {
            return;
        }

        if ($user->canEncode() && (int) $report->submitted_by === (int) $user->id) {
            return;
        }

        abort(403);
    }

    /**
     * @param  list<int|string>  $agendaItemIds
     * @return Collection<int, int>
     */
    protected function resolveAllowedAgendaIds(
        int $boardMemberId,
        array $agendaItemIds,
        ?BoardMemberCommitteeReport $existingReport = null,
    ): Collection {
        $requestedIds = collect($agendaItemIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($requestedIds->isEmpty()) {
            return collect();
        }

        $keptIds = $existingReport
            ? $existingReport->agendaItems()->pluck('agenda_items.id')->map(fn ($id) => (int) $id)
            : collect();

        $openIds = $this->dashboard->chairmanshipAgendasNeedingReportQueryForBoardMember($boardMemberId)
            ->whereIn('id', $requestedIds->all())
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        $allowedIds = $requestedIds
            ->filter(fn (int $id) => $openIds->contains($id) || $keptIds->contains($id))
            ->values();

        $chairIds = $this->dashboard->chairmanshipAgendaQueryForBoardMember($boardMemberId)
            ->whereIn('id', $requestedIds->all())
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        if ($requestedIds->diff($chairIds)->isNotEmpty() || $requestedIds->diff($allowedIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'agenda_item_ids' => ['Select agendas from the chairmanship that do not already have a committee report.'],
            ]);
        }

        $committees = AgendaItem::query()
            ->whereIn('id', $allowedIds->all())
            ->pluck('committee_referred')
            ->map(fn ($name) => mb_strtolower(trim((string) $name)))
            ->unique()
            ->values();

        if ($committees->count() > 1) {
            throw ValidationException::withMessages([
                'agenda_item_ids' => ['Tagged agendas must belong to the same committee so they share one committee report.'],
            ]);
        }

        return $allowedIds;
    }

    /**
     * @param  list<int>  $agendaItemIds
     */
    protected function syncAgendaAttachments(
        BoardMemberCommitteeReport $report,
        User $user,
        array $agendaItemIds,
        string $contents,
        int $fileSize,
        bool $clearPrevious,
    ): void {
        $previousIds = $clearPrevious
            ? $report->agendaItems()->pluck('agenda_items.id')->map(fn ($id) => (int) $id)->all()
            : [];

        $removedIds = array_values(array_diff($previousIds, $agendaItemIds));

        if ($removedIds !== []) {
            $this->restoreRemovedAgendas($report, $removedIds, $user->id);
            $this->clearAgendaReportFields($removedIds, deleteSharedFile: false);
        }

        $this->deleteSessionFilesForReport($report);

        $report->agendaItems()->sync($agendaItemIds);

        if ($agendaItemIds === []) {
            $report->forceFill([
                'original_filename' => $report->original_filename ?: 'committee-report.pdf',
                'previous_ob_placements' => [],
            ])->save();

            return;
        }

        $agendas = AgendaItem::query()
            ->whereIn('id', $agendaItemIds)
            ->orderBy('id')
            ->get()
            ->sortBy(function (AgendaItem $agenda): string {
                $no = trim((string) $agenda->tracking_no);

                if ($no !== '' && ctype_digit($no)) {
                    return str_pad($no, 10, '0', STR_PAD_LEFT);
                }

                return $no !== '' ? $no : str_pad((string) $agenda->id, 10, '0', STR_PAD_LEFT);
            })
            ->values();

        // One shared file path for all tagged agendas (same local storage).
        $sharedPath = $report->pdf_path;

        $existingPrevious = is_array($report->previous_ob_placements) ? $report->previous_ob_placements : [];
        $previousPlacements = [];

        foreach ($agendas as $agenda) {
            $key = (string) $agenda->id;
            if (isset($existingPrevious[$key]) && in_array($agenda->id, $previousIds, true)) {
                $previousPlacements[$key] = $existingPrevious[$key];
            } else {
                $previousPlacements[$key] = $this->capturePreviousObPlacement($agenda);
            }

            $agenda->forceFill([
                'committee_report_pdf_path' => $sharedPath,
            ])->save();
        }

        foreach ($agendas as $agenda) {
            $this->lifecycle->handleAgendaSaved(
                $agenda->fresh(),
                ['committee_report_pdf_path'],
                $user->id,
            );
        }

        $agendas = $agendas->map(fn (AgendaItem $agenda) => $agenda->fresh())->values();
        $filename = $this->buildObStyleFilenameForAgendas($agendas);

        $report->forceFill([
            'original_filename' => $filename,
            'previous_ob_placements' => $previousPlacements,
        ])->save();

        $this->attachSharedSessionFile(
            $agendaItemIds,
            $contents,
            $filename,
            $user->id,
            $fileSize,
            $report->id,
        );
    }

    /**
     * @param  list<int>  $agendaItemIds
     */
    protected function attachSharedSessionFile(
        array $agendaItemIds,
        string $contents,
        string $filename,
        ?int $userId,
        int $fileSize,
        int $reportId,
    ): void {
        $sessionIds = AgendaObPlacement::query()
            ->whereIn('agenda_item_id', $agendaItemIds)
            ->whereNotNull('legislative_session_id')
            ->pluck('legislative_session_id')
            ->merge(
                ObBlock::query()
                    ->where(function ($query) use ($agendaItemIds): void {
                        $query->whereIn('agenda_item_id', $agendaItemIds);
                        foreach ($agendaItemIds as $agendaId) {
                            $query->orWhereJsonContains('content->agenda_item_ids', $agendaId);
                        }
                    })
                    ->with('obDocument:id,legislative_session_id')
                    ->get()
                    ->map(fn (ObBlock $block) => $block->obDocument?->legislative_session_id)
            )
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        foreach ($sessionIds as $sessionId) {
            $session = LegislativeSession::query()->find($sessionId);
            if ($session === null) {
                continue;
            }

            $this->sessionCommitteeReports->storeBytes(
                $contents,
                $session,
                $filename,
                'pdf',
                'application/pdf',
                $userId,
                $fileSize,
                $reportId,
            );
        }
    }

    /**
     * @return array{legislative_session_id: int|null, section: string|null}
     */
    protected function capturePreviousObPlacement(AgendaItem $agenda): array
    {
        $session = $this->sessionContainingAgenda($agenda);

        if ($session === null || ! $session->obDocument) {
            return [
                'legislative_session_id' => null,
                'section' => null,
            ];
        }

        return [
            'legislative_session_id' => $session->id,
            'section' => $this->documentService->sectionForAgendaInDocument($session->obDocument, $agenda->id),
        ];
    }

    protected function sessionContainingAgenda(AgendaItem $agenda): ?LegislativeSession
    {
        $sessions = LegislativeSession::query()
            ->with('obDocument')
            ->whereIn('status', ['draft', 'scheduled'])
            ->whereDate('session_date', '>=', now()->toDateString())
            ->whereHas('obDocument')
            ->orderBy('session_date')
            ->orderBy('session_time')
            ->orderBy('id')
            ->get();

        foreach ($sessions as $session) {
            if ($session->obDocument && $this->documentService->documentContainsAgenda($session->obDocument, $agenda->id)) {
                return $session;
            }
        }

        return null;
    }

    protected function restorePreviousObPlacements(BoardMemberCommitteeReport $report, ?int $userId): void
    {
        $previous = is_array($report->previous_ob_placements) ? $report->previous_ob_placements : [];

        foreach ($report->agendaItems as $agenda) {
            $meta = $previous[(string) $agenda->id] ?? null;
            $this->restoreAgendaObPlacement($agenda, is_array($meta) ? $meta : null, $userId);
        }
    }

    /**
     * @param  list<int>  $agendaIds
     */
    protected function restoreRemovedAgendas(BoardMemberCommitteeReport $report, array $agendaIds, ?int $userId): void
    {
        $previous = is_array($report->previous_ob_placements) ? $report->previous_ob_placements : [];
        $agendas = AgendaItem::query()->whereIn('id', $agendaIds)->get();

        foreach ($agendas as $agenda) {
            $meta = $previous[(string) $agenda->id] ?? null;
            $this->restoreAgendaObPlacement($agenda, is_array($meta) ? $meta : null, $userId);
        }
    }

    /**
     * @param  array{legislative_session_id?: int|null, section?: string|null}|null  $meta
     */
    protected function restoreAgendaObPlacement(AgendaItem $agenda, ?array $meta, ?int $userId): void
    {
        $currentSession = $this->sessionContainingAgenda($agenda);
        $previousSessionId = isset($meta['legislative_session_id']) ? (int) $meta['legislative_session_id'] : null;
        $previousSection = isset($meta['section']) && filled($meta['section']) ? (string) $meta['section'] : null;

        if ($previousSessionId && $previousSection && $previousSection !== 'committee_reports') {
            $session = LegislativeSession::query()->with('obDocument')->find($previousSessionId);
            if ($session?->obDocument) {
                $this->lifecycle->relocateAgendaInSession($agenda->fresh(), $session, $previousSection, $userId);

                return;
            }
        }

        // Was not on OB before this report (or previous section unknown) — remove from Committee Reports.
        if ($currentSession?->obDocument) {
            $currentSection = $this->documentService->sectionForAgendaInDocument(
                $currentSession->obDocument,
                $agenda->id,
            );

            if ($currentSection === 'committee_reports') {
                $this->documentService->removeAgendaFromDocument(
                    $currentSession->obDocument,
                    $agenda,
                    $userId,
                    'automatic',
                );

                $agenda->forceFill([
                    'ob_lifecycle_stage' => filled($agenda->committee_referred)
                        ? AgendaItem::OB_STAGE_UNASSIGNED
                        : null,
                ])->saveQuietly();
            }
        }
    }

    /**
     * @param  list<int>  $agendaItemIds
     */
    protected function clearAgendaReportFields(array $agendaItemIds, bool $deleteSharedFile): void
    {
        if ($agendaItemIds === []) {
            return;
        }

        $agendas = AgendaItem::query()->whereIn('id', $agendaItemIds)->get();

        foreach ($agendas as $agenda) {
            $path = $agenda->committee_report_pdf_path;

            // Only delete per-agenda copies; shared BM report files are owned by the report record.
            if ($deleteSharedFile && filled($path) && $this->isAgendaOwnedPdfPath($path) && Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }

            $agenda->forceFill([
                'committee_report_pdf_path' => null,
            ])->save();
        }
    }

    protected function isAgendaOwnedPdfPath(string $path): bool
    {
        return str_starts_with(str_replace('\\', '/', $path), 'agenda/');
    }

    protected function deleteSessionFilesForReport(BoardMemberCommitteeReport $report): void
    {
        $files = LegislativeSessionCommitteeReportFile::query()
            ->where('board_member_committee_report_id', $report->id)
            ->get();

        foreach ($files as $file) {
            $this->sessionCommitteeReports->delete($file);
        }
    }

    /**
     * @param  Collection<int, AgendaItem>  $agendas
     */
    public function buildObStyleFilenameForAgendas(Collection $agendas): string
    {
        if ($agendas->isEmpty()) {
            return 'committee-report.pdf';
        }

        $first = $agendas->first();
        $rowNo = $this->resolveCommitteeReportRowNo($first) ?? 1;
        $committee = $this->committeeFilenameLabel($first->committee_referred);
        $numbers = $agendas
            ->map(fn (AgendaItem $agenda) => $this->formatAgendaNumber(ObAgendaSnapshot::agendaNo($agenda)))
            ->unique()
            ->values()
            ->all();

        return sprintf('%d. %s-Agenda %s.pdf', $rowNo, $committee, implode(', ', $numbers));
    }

    public function buildObStyleFilename(AgendaItem $agenda): string
    {
        return $this->buildObStyleFilenameForAgendas(collect([$agenda]));
    }

    protected function resolveCommitteeReportRowNo(AgendaItem $agenda): ?int
    {
        $blocks = ObBlock::query()
            ->where('type', ObBlockType::CommitteeReport)
            ->where(function ($query) use ($agenda): void {
                $query->where('agenda_item_id', $agenda->id)
                    ->orWhereJsonContains('content->agenda_item_ids', $agenda->id);
            })
            ->orderByDesc('id')
            ->get();

        foreach ($blocks as $block) {
            $row = (int) ($block->content['row_no'] ?? 0);
            if ($row > 0) {
                return $row;
            }
        }

        return null;
    }

    protected function committeeFilenameLabel(?string $committee): string
    {
        $label = trim((string) $committee);
        $label = preg_replace('/^(sp\s+)?committee\s+on\s+/i', '', $label) ?? $label;
        $label = mb_strtoupper(trim($label));
        $label = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $label);
        $label = preg_replace('/\s+/', ' ', $label) ?? $label;

        return $label !== '' ? $label : 'COMMITTEE';
    }

    protected function formatAgendaNumber(string $agendaNo): string
    {
        $agendaNo = trim($agendaNo);

        if ($agendaNo !== '' && ctype_digit($agendaNo)) {
            return str_pad($agendaNo, 3, '0', STR_PAD_LEFT);
        }

        return $agendaNo !== '' ? $agendaNo : '000';
    }

    public function streamPdf(BoardMemberCommitteeReport $report): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($report->pdf_path), 404);

        $filename = $report->original_filename ?: basename($report->pdf_path);

        return Storage::disk('local')->response(
            $report->pdf_path,
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }
}

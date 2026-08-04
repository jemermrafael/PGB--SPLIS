<?php

namespace App\Services;

use App\Models\AgendaItem;
use App\Models\LegislativeSession;
use App\Support\ActivityLogger;
use App\Support\AgendaDeadline;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AgendaLifecycleService
{
    /** @var list<string> */
    public const LIFECYCLE_FIELDS = [
        'committee_referred',
        'date_of_referral',
        'committee_report_url',
        'committee_report_pdf_path',
        'is_urgent_request',
        'status',
    ];

    /** @var list<string> */
    private const UNASSIGNED_SECTIONS = ['unassigned_regular', 'unassigned_urgent'];

    public function __construct(
        private ObDocumentService $documentService,
    ) {}

    /**
     * @param  list<string>  $changedFields
     */
    public function handleAgendaSaved(AgendaItem $agenda, array $changedFields = [], ?int $userId = null): void
    {
        if ($agenda->isObLifecycleResolved()) {
            if ($agenda->ob_lifecycle_stage !== AgendaItem::OB_STAGE_RESOLVED) {
                $agenda->forceFill(['ob_lifecycle_stage' => AgendaItem::OB_STAGE_RESOLVED])->saveQuietly();
            }

            return;
        }

        if ($this->lifecycleFieldsChanged($changedFields)) {
            $agenda->forceFill(['ob_manual_override_at' => null])->saveQuietly();
            $agenda->refresh();
        }

        if ($agenda->hasObManualOverride()) {
            return;
        }

        if (in_array('status', $changedFields, true) && $agenda->status === AgendaItem::STATUS_DONE) {
            $agenda->forceFill(['ob_lifecycle_stage' => AgendaItem::OB_STAGE_RESOLVED])->saveQuietly();

            return;
        }

        if ($this->committeeReportAttachmentChanged($changedFields) && $this->hasCommitteeReport($agenda)) {
            $agenda->forceFill(['ob_lifecycle_stage' => AgendaItem::OB_STAGE_COMMITTEE_REPORT])->saveQuietly();
            $this->handleCommitteeReportPlacement($agenda->fresh(), $userId);

            return;
        }

        if (in_array('is_urgent_request', $changedFields, true) && $agenda->is_urgent_request) {
            $this->handleUrgentRequestPlacement($agenda->fresh(), $userId);
        }

        if (! filled($agenda->committee_referred)) {
            return;
        }

        if (blank($agenda->ob_lifecycle_stage)) {
            $agenda->forceFill(['ob_lifecycle_stage' => AgendaItem::OB_STAGE_UNASSIGNED])->saveQuietly();
        }

        if ($this->shouldInitialUnassignedPlacement($agenda, $changedFields)) {
            $this->syncToNearestUpcomingSession($agenda->fresh(), $userId);
        }
    }

    /**
     * Place (and optionally relocate) eligible agendas into a session OB using lifecycle rules.
     *
     * @return array{added: int, relocated: int}
     */
    public function syncNewSession(
        LegislativeSession $session,
        ?int $userId = null,
        bool $clearManualOverrides = true,
    ): array {
        $added = 0;
        $relocated = 0;

        if (! in_array($session->status, ['draft', 'scheduled'], true)) {
            return compact('added', 'relocated');
        }

        $session->loadMissing(['obDocument', 'priorSession']);

        if (! $session->obDocument) {
            return compact('added', 'relocated');
        }

        AgendaItem::query()
            ->where('status', '!=', AgendaItem::STATUS_DONE)
            ->where(function ($query): void {
                $query->where(function ($committee): void {
                    $committee->whereNotNull('committee_referred')
                        ->where('committee_referred', '!=', '');
                })
                    ->orWhereNotNull('committee_report_url')
                    ->orWhereNotNull('committee_report_pdf_path')
                    ->orWhereHas('boardMemberCommitteeReports');
            })
            ->with(['lastObSyncedSession', 'obPlacements.legislativeSession', 'boardMemberCommitteeReports:id'])
            ->orderBy('id')
            ->chunkById(100, function ($agendas) use ($session, $userId, $clearManualOverrides, &$added, &$relocated): void {
                foreach ($agendas as $agenda) {
                    if ($agenda->status === AgendaItem::STATUS_LAPSED) {
                        continue;
                    }

                    $hasCommitteeReport = $this->hasCommitteeReport($agenda);

                    // Filed committee reports still belong in IV even when prescription days have elapsed.
                    if (! $hasCommitteeReport && ! $this->prescribedDaysPermit($agenda)) {
                        continue;
                    }

                    if ($agenda->isObLifecycleResolved()
                        || (blank($agenda->committee_referred) && ! $hasCommitteeReport)) {
                        continue;
                    }

                    $fresh = $agenda->fresh([
                        'lastObSyncedSession',
                        'obPlacements.legislativeSession',
                        'boardMemberCommitteeReports:id',
                    ]);

                    if ($fresh === null) {
                        continue;
                    }

                    if ($this->isAgendaInSession($fresh, $session)) {
                        $targetSection = $this->resolveTargetSection($fresh, $session);

                        if ($targetSection === null) {
                            continue;
                        }

                        $forceCommitteeReportRelocate = $targetSection === 'committee_reports'
                            && $this->hasCommitteeReport($fresh);

                        if ($fresh->hasObManualOverride() && ! $clearManualOverrides && ! $forceCommitteeReportRelocate) {
                            continue;
                        }

                        if ($clearManualOverrides || $forceCommitteeReportRelocate) {
                            $fresh->forceFill(['ob_manual_override_at' => null])->saveQuietly();
                            $fresh->refresh();
                        }

                        if ($this->ensureAgendaSectionsInSession($fresh, $session, $targetSection, $userId)) {
                            $relocated++;
                        }

                        continue;
                    }

                    if ($this->resolveTargetSection($fresh, $session) === null) {
                        continue;
                    }

                    if ($clearManualOverrides) {
                        $fresh->forceFill(['ob_manual_override_at' => null])->saveQuietly();
                        $fresh->refresh();
                    }

                    if ($this->syncAgendaToSession($fresh, $session, $userId)) {
                        $added++;
                    }
                }
            });

        $this->documentService->normalizeAgendaSections($session->obDocument);

        return compact('added', 'relocated');
    }

    public function nearestUpcomingSession(): ?LegislativeSession
    {
        return LegislativeSession::query()
            ->with('obDocument')
            ->whereIn('status', ['draft', 'scheduled'])
            ->whereDate('session_date', '>=', now()->toDateString())
            ->whereHas('obDocument')
            ->orderBy('session_date')
            ->orderBy('session_time')
            ->orderBy('id')
            ->first();
    }

    public function prescribedDaysPermit(AgendaItem $agenda): bool
    {
        if ($agenda->status === AgendaItem::STATUS_DONE || $agenda->status === AgendaItem::STATUS_LAPSED) {
            return false;
        }

        if (! $agenda->prescribed_days || $agenda->prescribed_days < 1) {
            return true;
        }

        if (! $agenda->due_date) {
            return true;
        }

        $daysLeft = AgendaDeadline::daysLeftLabel($agenda);

        if ($daysLeft === null || ! is_numeric($daysLeft)) {
            return true;
        }

        return (int) $daysLeft >= 0;
    }

    public function resolveTargetSection(AgendaItem $agenda, LegislativeSession $session): ?string
    {
        $hasCommitteeReport = $this->hasCommitteeReport($agenda);

        if (! filled($agenda->committee_referred) && ! $hasCommitteeReport) {
            return null;
        }

        if ($agenda->isObLifecycleResolved()) {
            return null;
        }

        if ($hasCommitteeReport) {
            return 'committee_reports';
        }

        if (! $this->prescribedDaysPermit($agenda)) {
            return null;
        }

        if (! $this->agendaWasPlacedBefore($agenda, $session)) {
            return $this->initialUnassignedSection($agenda);
        }

        return 'unfinished';
    }

    public function syncAgendaToSession(AgendaItem $agenda, LegislativeSession $session, ?int $userId = null): bool
    {
        $session->loadMissing('obDocument');

        if (! $session->obDocument) {
            return false;
        }

        if ($this->isAgendaInSession($agenda, $session)) {
            $section = $this->resolveTargetSection($agenda, $session);

            return $section !== null
                && $this->ensureAgendaSectionsInSession($agenda, $session, $section, $userId);
        }

        $section = $this->resolveTargetSection($agenda, $session);

        if ($section === null) {
            return false;
        }

        try {
            DB::transaction(function () use ($agenda, $session, $section, $userId): void {
                $this->documentService->addAgendaItems(
                    $session->obDocument,
                    [$agenda->id],
                    null,
                    $section,
                    null,
                    $userId,
                    'automatic',
                );

                $loggedSections = [$section];

                if ($section === 'committee_reports' && $this->shouldAlsoPlaceInUnfinished($agenda, $session)) {
                    $this->documentService->addAgendaItems(
                        $session->obDocument,
                        [$agenda->id],
                        null,
                        'unfinished',
                        null,
                        $userId,
                        'automatic',
                    );
                    $loggedSections[] = 'unfinished';
                }

                $this->updateAgendaAfterSync($agenda, $session, $section);
                $this->logAddedToOb($agenda, $session, $loggedSections, 'automatic', $userId);
            });
        } catch (ValidationException $exception) {
            Log::warning('Automatic OB sync failed for agenda item.', [
                'agenda_item_id' => $agenda->id,
                'legislative_session_id' => $session->id,
                'section' => $section,
                'errors' => $exception->errors(),
            ]);

            return false;
        }

        return true;
    }

    public function relocateAgendaInSession(
        AgendaItem $agenda,
        LegislativeSession $session,
        string $targetSection,
        ?int $userId = null,
    ): bool {
        if ($agenda->hasObManualOverride() || $agenda->isObLifecycleResolved()) {
            return false;
        }

        $session->loadMissing('obDocument');

        if (! $session->obDocument) {
            return false;
        }

        if (! $this->isAgendaInSession($agenda, $session)) {
            return $this->syncAgendaToSession($agenda, $session, $userId);
        }

        return $this->ensureAgendaSectionsInSession($agenda, $session, $targetSection, $userId);
    }

    /**
     * Ensure the agenda is in the target section, keeping unfinished when dual-placing with CR.
     */
    public function ensureAgendaSectionsInSession(
        AgendaItem $agenda,
        LegislativeSession $session,
        string $targetSection,
        ?int $userId = null,
    ): bool {
        if ($agenda->hasObManualOverride() || $agenda->isObLifecycleResolved()) {
            return false;
        }

        $session->loadMissing('obDocument');
        $document = $session->obDocument;

        if ($document === null) {
            return false;
        }

        $sections = $this->documentService->sectionsForAgendaInDocument($document, $agenda->id);
        $changed = false;
        $fromSection = $sections->first();
        $addedSections = [];

        try {
            DB::transaction(function () use (
                $agenda,
                $session,
                $document,
                $targetSection,
                $sections,
                $userId,
                &$changed,
                &$fromSection,
                &$addedSections,
            ): void {
                $mirrorUnfinished = $targetSection === 'committee_reports'
                    && $this->shouldAlsoPlaceInUnfinished($agenda, $session);

                if ($targetSection === 'committee_reports') {
                    // Drop unassigned/other exclusive homes, but keep unfinished when dual-placing.
                    foreach ($sections as $section) {
                        if ($section === 'committee_reports') {
                            continue;
                        }
                        if ($section === 'unfinished' && $mirrorUnfinished) {
                            continue;
                        }
                        $this->documentService->removeAgendaFromDocument(
                            $document,
                            $agenda,
                            $userId,
                            'relocation',
                            $section,
                        );
                        $changed = true;
                    }

                    if (! $this->documentService->agendaIsInSection($document->fresh() ?? $document, $agenda->id, 'committee_reports')) {
                        $this->documentService->addAgendaItems(
                            $document,
                            [$agenda->id],
                            null,
                            'committee_reports',
                            null,
                            $userId,
                            'automatic',
                        );
                        $addedSections[] = 'committee_reports';
                        $changed = true;
                    }

                    if ($mirrorUnfinished
                        && ! $this->documentService->agendaIsInSection($document->fresh() ?? $document, $agenda->id, 'unfinished')) {
                        $this->documentService->addAgendaItems(
                            $document,
                            [$agenda->id],
                            null,
                            'unfinished',
                            null,
                            $userId,
                            'automatic',
                        );
                        $addedSections[] = 'unfinished';
                        $changed = true;
                    }

                    // Already in unfinished and newly added to CR → one combined History entry.
                    if ($mirrorUnfinished
                        && in_array('committee_reports', $addedSections, true)
                        && $this->documentService->agendaIsInSection($document->fresh() ?? $document, $agenda->id, 'unfinished')
                        && ! in_array('unfinished', $addedSections, true)) {
                        $addedSections[] = 'unfinished';
                    }
                } else {
                    if ($sections->contains($targetSection) && $sections->count() === 1) {
                        return;
                    }

                    // Standard relocate: remove all, then add target.
                    if ($sections->isNotEmpty() && ! ($sections->count() === 1 && $sections->first() === $targetSection)) {
                        $this->documentService->removeAgendaFromDocument($document, $agenda, $userId, 'relocation');
                        $changed = true;
                    }

                    if (! $this->documentService->agendaIsInSection($document->fresh() ?? $document, $agenda->id, $targetSection)) {
                        $this->documentService->addAgendaItems(
                            $document,
                            [$agenda->id],
                            null,
                            $targetSection,
                            null,
                            $userId,
                            'automatic',
                        );
                        $addedSections[] = $targetSection;
                        $changed = true;
                    }
                }

                if ($changed) {
                    $this->updateAgendaAfterSync($agenda, $session, $targetSection);

                    if ($addedSections !== []) {
                        $this->logAddedToOb($agenda, $session, $addedSections, 'automatic', $userId);
                    } elseif ($fromSection !== null && $fromSection !== $targetSection) {
                        $this->logRelocatedInOb($agenda, $session, $fromSection, $targetSection, $userId);
                    }
                }
            });
        } catch (ValidationException $exception) {
            Log::warning('Automatic OB section ensure failed for agenda item.', [
                'agenda_item_id' => $agenda->id,
                'legislative_session_id' => $session->id,
                'target_section' => $targetSection,
                'errors' => $exception->errors(),
            ]);

            return false;
        }

        return $changed;
    }

    /**
     * Agendas under IV. Committee Reports also appear under A. Unfinished Business.
     */
    protected function shouldAlsoPlaceInUnfinished(AgendaItem $agenda, LegislativeSession $session): bool
    {
        if (! $this->hasCommitteeReport($agenda) || $agenda->isObLifecycleResolved()) {
            return false;
        }

        return true;
    }

    /**
     * @param  string|list<string>  $section
     */
    public function logAddedToOb(
        AgendaItem $agenda,
        LegislativeSession $session,
        string|array $section,
        string $source,
        ?int $userId = null,
    ): void {
        $sections = array_values(array_unique(is_array($section) ? $section : [$section]));
        $labels = collect($sections)
            ->map(fn (string $key) => (string) config('order_of_business.agenda_sections.'.$key, $key))
            ->values()
            ->all();

        ActivityLogger::log('agenda.added_to_ob', $agenda, ActivityLogger::agendaObProperties($agenda, [
            'source' => $source,
            'section' => $sections[0] ?? null,
            'sections' => $sections,
            'section_label' => $labels[0] ?? null,
            'section_labels' => $labels,
            'session_id' => $session->id,
            'session_title' => $session->displayTitle(),
            'session_date' => $session->session_date?->format('Y-m-d'),
        ]), $userId);
    }

    public function logRelocatedInOb(
        AgendaItem $agenda,
        LegislativeSession $session,
        ?string $fromSection,
        string $toSection,
        ?int $userId = null,
    ): void {
        ActivityLogger::log('agenda.ob_relocated', $agenda, ActivityLogger::agendaObProperties($agenda, [
            'source' => 'automatic',
            'from_section' => $fromSection,
            'from_section_label' => $fromSection
                ? config('order_of_business.agenda_sections.'.$fromSection, $fromSection)
                : null,
            'to_section' => $toSection,
            'to_section_label' => config('order_of_business.agenda_sections.'.$toSection, $toSection),
            'session_id' => $session->id,
            'session_title' => $session->displayTitle(),
            'session_date' => $session->session_date?->format('Y-m-d'),
        ]), $userId);
    }

    public function isSessionAfter(LegislativeSession $earlier, LegislativeSession $later): bool
    {
        if ($earlier->id === $later->id) {
            return false;
        }

        if ($later->session_date->gt($earlier->session_date)) {
            return true;
        }

        return $later->session_date->eq($earlier->session_date) && $later->id > $earlier->id;
    }

    protected function handleCommitteeReportPlacement(AgendaItem $agenda, ?int $userId = null): void
    {
        if ($agenda->hasObManualOverride() || $agenda->isObLifecycleResolved()) {
            return;
        }

        $session = $this->sessionContainingAgenda($agenda) ?? $this->nearestUpcomingSession();

        if ($session === null) {
            return;
        }

        if ($this->isAgendaInSession($agenda, $session)) {
            $this->relocateAgendaInSession($agenda, $session, 'committee_reports', $userId);

            return;
        }

        // Force IV. Committee Reports even on first placement (resolveTargetSection already prefers it).
        $this->syncAgendaToSession($agenda, $session, $userId);
    }

    protected function handleUrgentRequestPlacement(AgendaItem $agenda, ?int $userId = null): void
    {
        if ($agenda->hasObManualOverride() || ! filled($agenda->committee_referred)) {
            return;
        }

        $session = $this->sessionContainingAgenda($agenda) ?? $this->nearestUpcomingSession();

        if ($session === null) {
            return;
        }

        if ($this->isAgendaInSession($agenda, $session)) {
            $this->relocateAgendaInSession($agenda, $session, 'unassigned_urgent', $userId);

            return;
        }

        if (blank($agenda->ob_lifecycle_stage)) {
            $agenda->forceFill(['ob_lifecycle_stage' => AgendaItem::OB_STAGE_UNASSIGNED])->saveQuietly();
        }

        $this->syncAgendaToSession($agenda, $session, $userId);
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

    protected function initialUnassignedSection(AgendaItem $agenda): string
    {
        return $agenda->is_urgent_request ? 'unassigned_urgent' : 'unassigned_regular';
    }

    protected function updateAgendaAfterSync(AgendaItem $agenda, LegislativeSession $session, string $section): void
    {
        $stage = match ($section) {
            'committee_reports' => AgendaItem::OB_STAGE_COMMITTEE_REPORT,
            'unfinished' => AgendaItem::OB_STAGE_UNFINISHED,
            default => AgendaItem::OB_STAGE_UNASSIGNED,
        };

        $agenda->forceFill([
            'ob_lifecycle_stage' => $stage,
            'last_ob_synced_session_id' => $session->id,
        ])->saveQuietly();
    }

    protected function syncToNearestUpcomingSession(AgendaItem $agenda, ?int $userId = null): void
    {
        if ($agenda->hasObManualOverride() || $agenda->isObLifecycleResolved()) {
            return;
        }

        $session = $this->nearestUpcomingSession();

        if ($session === null) {
            return;
        }

        $this->syncAgendaToSession($agenda, $session, $userId);
    }

    protected function shouldInitialUnassignedPlacement(AgendaItem $agenda, array $changedFields): bool
    {
        if (blank($agenda->committee_referred)) {
            return false;
        }

        $session = $this->nearestUpcomingSession();

        if ($session === null || $this->isAgendaInSession($agenda, $session)) {
            return false;
        }

        $targetSection = $this->resolveTargetSection($agenda, $session);

        if ($agenda->last_ob_synced_session_id === null && ! $this->agendaWasPlacedBefore($agenda, $session)) {
            return in_array($targetSection, self::UNASSIGNED_SECTIONS, true);
        }

        return in_array('committee_referred', $changedFields, true)
            && in_array($targetSection, self::UNASSIGNED_SECTIONS, true);
    }

    protected function shouldSyncAgendaToSession(AgendaItem $agenda, LegislativeSession $session): bool
    {
        if ($agenda->isObLifecycleResolved() || blank($agenda->committee_referred)) {
            return false;
        }

        if (! $this->prescribedDaysPermit($agenda)) {
            return false;
        }

        if ($this->isAgendaInSession($agenda, $session)) {
            return false;
        }

        return $this->resolveTargetSection($agenda, $session) !== null;
    }

    protected function agendaWasPlacedBefore(AgendaItem $agenda, LegislativeSession $session): bool
    {
        if ($agenda->last_ob_synced_session_id !== null) {
            $last = $agenda->lastObSyncedSession;

            if ($last && $last->id !== $session->id && $this->isSessionAfter($last, $session)) {
                return true;
            }
        }

        foreach ($agenda->obPlacements as $placement) {
            $placedSession = $placement->legislativeSession;

            if ($placedSession
                && $placedSession->id !== $session->id
                && $this->isSessionAfter($placedSession, $session)) {
                return true;
            }
        }

        return false;
    }

    protected function isAgendaInSession(AgendaItem $agenda, LegislativeSession $session): bool
    {
        if (! $session->obDocument) {
            return false;
        }

        return $this->documentService->documentContainsAgenda($session->obDocument, $agenda->id);
    }

    /**
     * @param  list<string>  $changedFields
     */
    protected function lifecycleFieldsChanged(array $changedFields): bool
    {
        return array_intersect($changedFields, self::LIFECYCLE_FIELDS) !== [];
    }

    /**
     * @param  list<string>  $changedFields
     */
    protected function committeeReportAttachmentChanged(array $changedFields): bool
    {
        return in_array('committee_report_url', $changedFields, true)
            || in_array('committee_report_pdf_path', $changedFields, true);
    }

    protected function hasCommitteeReport(AgendaItem $agenda): bool
    {
        if (filled($agenda->committee_report_url) || filled($agenda->committee_report_pdf_path)) {
            return true;
        }

        if ($agenda->relationLoaded('boardMemberCommitteeReports')) {
            return $agenda->boardMemberCommitteeReports->isNotEmpty();
        }

        if (! $agenda->exists) {
            return false;
        }

        return $agenda->boardMemberCommitteeReports()->exists();
    }
}

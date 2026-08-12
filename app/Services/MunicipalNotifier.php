<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\AgendaObPlacement;
use App\Models\LegislativeSession;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\AgendaDeadline;
use App\Support\MunicipalRequestAccess;
use App\Support\ObAgendaAddedDigest;
use Illuminate\Support\Collection;

class MunicipalNotifier
{
    public function __construct(
        protected EmailNotificationService $emails,
    ) {}

    public function notifyCommitteeReferral(AgendaItem $agenda, ?string $previousCommittee = null): void
    {
        // Immediate per-request alerts were retired to cut noise.
        // Municipal accounts get committee referrals via Scheduled Committee Referral digests instead.
    }

    /**
     * One in-app notification (+ optional email) per municipal viewer for agendas in a scheduled referral batch.
     *
     * @param  \Illuminate\Support\Collection<int, array{agenda: AgendaItem, committee: \App\Models\Committee}>  $rows
     */
    public function notifyScheduledCommitteeReferrals(
        Collection $rows,
        ?LegislativeSession $session = null,
        bool $sendEmail = false,
    ): void {
        $rows = $rows
            ->filter(fn ($row) => is_array($row)
                && ($row['agenda'] ?? null) instanceof AgendaItem
                && ($row['committee'] ?? null) instanceof \App\Models\Committee)
            ->unique(fn (array $row) => $row['agenda']->id)
            ->values();

        if ($rows->isEmpty()) {
            return;
        }

        /** @var array<int, array{user: User, rows: list<array{agenda: AgendaItem, committee: \App\Models\Committee}>}> $byUser */
        $byUser = [];

        foreach ($rows as $row) {
            foreach ($this->usersForAgenda($row['agenda']) as $user) {
                $byUser[$user->id] ??= ['user' => $user, 'rows' => []];
                $byUser[$user->id]['rows'][] = $row;
            }
        }

        foreach ($byUser as $entry) {
            $this->notifyScheduledCommitteeReferralsToUser(
                $entry['user'],
                collect($entry['rows']),
                $session,
                $sendEmail,
            );
        }
    }

    /**
     * @param  Collection<int, array{agenda: AgendaItem, committee: \App\Models\Committee}>  $rows
     */
    protected function notifyScheduledCommitteeReferralsToUser(
        User $user,
        Collection $rows,
        ?LegislativeSession $session = null,
        bool $sendEmail = false,
    ): void {
        $rows = $rows
            ->unique(fn (array $row) => $row['agenda']->id)
            ->sortBy(fn (array $row) => [
                (int) preg_replace('/\D+/', '', (string) $row['agenda']->tracking_no),
                $row['agenda']->id,
            ])
            ->values();

        if ($rows->isEmpty()) {
            return;
        }

        $parts = $rows
            ->map(function (array $row): string {
                $label = $row['agenda']->displayLabel();
                $committee = trim((string) ($row['committee']->name ?? ''));

                return $committee !== ''
                    ? sprintf('%s was referred to %s', $label, $committee)
                    : sprintf('%s was referred to a committee', $label);
            })
            ->filter()
            ->values();

        if ($parts->isEmpty()) {
            return;
        }

        $body = $parts->implode('. ').'.';
        $title = $parts->count() === 1
            ? 'Your request was referred to a committee'
            : 'Your requests were referred to committees';
        $type = UserNotification::TYPE_SCHEDULED_COMMITTEE_REFERRAL;
        $singleAgenda = $rows->count() === 1 ? $rows->first()['agenda'] : null;
        $link = $singleAgenda
            ? route('municipal.requests.show', $singleAgenda, absolute: false)
            : route('municipal.requests.index', absolute: false);

        $notification = UserNotification::query()->create([
            'user_id' => $user->id,
            'agenda_item_id' => $singleAgenda?->id,
            'legislative_session_id' => $session?->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'link' => $link,
            'read_at' => null,
        ]);

        if (! $sendEmail) {
            return;
        }

        $this->emails->sendForNotification(
            $user,
            $notification,
            EmailNotificationSettings::AUDIENCE_MUNICIPAL,
            force: true,
            vars: [
                'title' => $title,
                'body' => $body,
                'summary' => $body,
                'email_subject' => $title,
                'label' => $parts->implode(', '),
            ],
            emailType: $type,
        );
    }

    public function notifyAgendaPublished(AgendaItem $agenda): void
    {
        $agenda->loadMissing(['resolution', 'ordinance', 'appropriationOrdinance']);

        if (! $agenda->isPublished()) {
            return;
        }

        $target = $agenda->publishedTargetLabel();

        if ($target === null) {
            return;
        }

        $label = $agenda->displayLabel();
        $emailType = $this->emails->resolvePublishedEmailType(
            EmailNotificationSettings::AUDIENCE_MUNICIPAL,
            $target,
        );
        $number = trim((string) ($agenda->reso_ord_ao_no ?? ''));
        $series = (int) ($agenda->reso_ord_ao_series ?? 0);
        $numberSuffix = '';
        if ($number !== '' && $series > 0) {
            $numberSuffix = ' ('.$number.' s. '.$series.')';
        } elseif ($number !== '') {
            $numberSuffix = ' ('.$number.')';
        }

        $title = match ($emailType) {
            EmailNotificationSettings::TYPE_RESOLUTION_PUBLISHED => 'Agenda was published to Resolution',
            EmailNotificationSettings::TYPE_APPROPRIATION_ORDINANCE_PUBLISHED => 'New Appropriation Ordinance published',
            default => 'New Ordinance published',
        };

        $body = match ($emailType) {
            EmailNotificationSettings::TYPE_RESOLUTION_PUBLISHED => sprintf('%s was published as a Resolution%s.', $label, $numberSuffix),
            EmailNotificationSettings::TYPE_APPROPRIATION_ORDINANCE_PUBLISHED => sprintf('%s was published as an Appropriation Ordinance%s.', $label, $numberSuffix),
            default => sprintf('%s was published as %s%s.', $label, $target, $numberSuffix),
        };

        foreach ($this->usersForAgenda($agenda) as $user) {
            $notification = UserNotification::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'agenda_item_id' => $agenda->id,
                    'type' => UserNotification::TYPE_AGENDA_PUBLISHED,
                ],
                [
                    'title' => $title,
                    'body' => $body,
                    'link' => route('municipal.requests.show', $agenda, absolute: false),
                ],
            );

            $this->emails->sendForNotification(
                $user,
                $notification,
                EmailNotificationSettings::AUDIENCE_MUNICIPAL,
                vars: [
                    'label' => $label,
                    'target' => $target,
                    'number_suffix' => $numberSuffix,
                ],
                emailType: $emailType,
            );
        }
    }

    public function notifyAgendaAddedToOb(AgendaItem $agenda, LegislativeSession $session, ?string $section = null): void
    {
        $this->notifyAgendasAddedToOb([$agenda], $session, $section);
    }

    /**
     * One in-app notification + email per municipal user for agendas in this batch.
     * Later batches for the same session create a new notification (not merged).
     * Agendas already covered by a prior digest for this user/session are skipped.
     *
     * @param  iterable<int, AgendaItem>  $agendas
     */
    public function notifyAgendasAddedToOb(iterable $agendas, LegislativeSession $session, ?string $section = null): void
    {
        $session->loadMissing('obDocument');

        if (! $session->isNotifiableForObAgendaAdds()) {
            return;
        }

        $agendas = collect($agendas)
            ->filter(fn ($agenda) => $agenda instanceof AgendaItem)
            ->unique(fn (AgendaItem $agenda) => $agenda->id)
            ->values();

        if ($agendas->isEmpty()) {
            return;
        }

        $agendas = AgendaItem::query()
            ->with('boardMemberCommitteeReports:id')
            ->whereIn('id', $agendas->pluck('id'))
            ->get()
            ->sortBy(fn (AgendaItem $agenda) => [(int) preg_replace('/\D+/', '', (string) $agenda->tracking_no), $agenda->id])
            ->values();

        $sectionsByAgendaId = $this->resolveSectionsForAgendas($agendas, $session, $section);

        $sessionTitle = $session->displayTitle();
        $byUser = [];

        foreach ($agendas as $agenda) {
            foreach ($this->usersForAgenda($agenda) as $user) {
                $byUser[$user->id] ??= ['user' => $user, 'agendas' => collect()];
                $byUser[$user->id]['agendas']->put($agenda->id, $agenda);
            }
        }

        foreach ($byUser as $entry) {
            /** @var User $user */
            $user = $entry['user'];
            /** @var Collection<int, AgendaItem> $userAgendas */
            $userAgendas = ObAgendaAddedDigest::filterUnnotified(
                $user,
                $session,
                $entry['agendas']
                    ->sortBy(fn (AgendaItem $agenda) => [(int) preg_replace('/\D+/', '', (string) $agenda->tracking_no), $agenda->id])
                    ->values(),
            );

            if ($userAgendas->isEmpty()) {
                continue;
            }

            $lines = $userAgendas
                ->map(function (AgendaItem $agenda) use ($sectionsByAgendaId): string {
                    return $this->formatAgendaObLine(
                        $agenda,
                        $sectionsByAgendaId[$agenda->id] ?? null,
                    );
                })
                ->filter()
                ->values()
                ->all();

            if ($lines === []) {
                continue;
            }

            $title = 'Your request was added to the Order of Business';
            $hasCommitteeReport = $userAgendas->contains(
                fn (AgendaItem $agenda) => $this->agendaAppearsWithCommitteeReport(
                    $agenda,
                    $sectionsByAgendaId[$agenda->id] ?? null,
                ),
            );

            if ($hasCommitteeReport) {
                $title = 'Your request was added under Committee Reports';
            }

            $summary = $this->buildObAddedSummary($sessionTitle, $lines);
            $link = $userAgendas->count() === 1
                ? route('municipal.requests.show', $userAgendas->first(), absolute: false)
                : route('municipal.requests.index', absolute: false);

            $notification = UserNotification::query()->create([
                'user_id' => $user->id,
                'legislative_session_id' => $session->id,
                'type' => UserNotification::TYPE_AGENDA_ADDED_TO_OB,
                'agenda_item_id' => null,
                'title' => $title,
                'body' => $summary,
                'link' => $link,
                'read_at' => null,
            ]);

            $this->emails->sendForNotification(
                $user,
                $notification,
                EmailNotificationSettings::AUDIENCE_MUNICIPAL,
                force: true,
                vars: [
                    'label' => $userAgendas
                        ->map(fn (AgendaItem $agenda) => $agenda->displayLabel())
                        ->filter()
                        ->implode(', '),
                    'summary' => $summary,
                    'session' => $sessionTitle,
                    'email_subject' => $title,
                ],
            );
        }
    }

    /**
     * @param  Collection<int, AgendaItem>  $agendas
     * @return array<int, string|null>
     */
    protected function resolveSectionsForAgendas(Collection $agendas, LegislativeSession $session, ?string $section): array
    {
        if ($section !== null && $section !== '') {
            return $agendas
                ->mapWithKeys(fn (AgendaItem $agenda) => [$agenda->id => $section])
                ->all();
        }

        $placements = AgendaObPlacement::query()
            ->where('legislative_session_id', $session->id)
            ->whereIn('agenda_item_id', $agendas->pluck('id'))
            ->orderByDesc('id')
            ->get(['agenda_item_id', 'section'])
            ->unique('agenda_item_id')
            ->keyBy('agenda_item_id');

        return $agendas
            ->mapWithKeys(function (AgendaItem $agenda) use ($placements) {
                return [$agenda->id => $placements->get($agenda->id)?->section];
            })
            ->all();
    }

    protected function formatAgendaObLine(AgendaItem $agenda, ?string $section): string
    {
        $label = $agenda->displayLabel();
        $sectionLabel = $this->sectionLabel($section);
        $withReport = $this->agendaAppearsWithCommitteeReport($agenda, $section);

        if ($sectionLabel === null) {
            return $withReport
                ? sprintf('%s (with committee report)', $label)
                : $label;
        }

        return $withReport
            ? sprintf('%s — %s (with committee report)', $label, $sectionLabel)
            : sprintf('%s — %s', $label, $sectionLabel);
    }

    /**
     * @param  list<string>  $lines
     */
    protected function buildObAddedSummary(string $sessionTitle, array $lines): string
    {
        if (count($lines) === 1) {
            return sprintf('%s was added to %s.', $lines[0], $sessionTitle);
        }

        return sprintf(
            "The following were added to %s:\n%s",
            $sessionTitle,
            collect($lines)->map(fn (string $line) => '• '.$line)->implode("\n"),
        );
    }

    protected function sectionLabel(?string $section): ?string
    {
        if ($section === null || $section === '') {
            return null;
        }

        $label = config('order_of_business.agenda_sections.'.$section);

        return is_string($label) && $label !== '' ? $label : $section;
    }

    protected function agendaAppearsWithCommitteeReport(AgendaItem $agenda, ?string $section): bool
    {
        if ($section === 'committee_reports') {
            return true;
        }

        if (filled($agenda->committee_report_url) || filled($agenda->committee_report_pdf_path)) {
            return true;
        }

        return $agenda->relationLoaded('boardMemberCommitteeReports')
            ? $agenda->boardMemberCommitteeReports->isNotEmpty()
            : $agenda->boardMemberCommitteeReports()->exists();
    }

    public function notifyAgendaExpiringSoon(AgendaItem $agenda): void
    {
        if (! AgendaDeadline::isWithinExpiringSoonWindow($agenda->due_date, $agenda->status)) {
            return;
        }

        $daysLeft = is_numeric($agenda->days_left_label) ? (int) $agenda->days_left_label : null;
        $daysLeftSuffix = $daysLeft === null
            ? ''
            : ' ('.$daysLeft.' day'.($daysLeft === 1 ? '' : 's').' left)';
        $dueDate = $agenda->due_date?->format('F j, Y') ?? '';
        $label = $agenda->displayLabel();
        $body = sprintf('%s is due on %s%s.', $label, $dueDate, $daysLeftSuffix);

        foreach ($this->usersForAgenda($agenda) as $user) {
            $notification = UserNotification::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'agenda_item_id' => $agenda->id,
                    'type' => UserNotification::TYPE_AGENDA_EXPIRING_SOON,
                ],
                [
                    'title' => 'Request deadline approaching',
                    'body' => $body,
                    'link' => route('municipal.requests.show', $agenda, absolute: false),
                ],
            );

            $this->emails->sendForNotification(
                $user,
                $notification,
                EmailNotificationSettings::AUDIENCE_MUNICIPAL,
                vars: [
                    'label' => $label,
                    'due_date' => $dueDate,
                    'days_left_suffix' => $daysLeftSuffix,
                ],
            );
        }
    }

    /** @return Collection<int, User> */
    protected function usersForAgenda(AgendaItem $agenda): Collection
    {
        $sender = trim((string) ($agenda->sender ?? ''));

        if ($sender === '') {
            return collect();
        }

        return User::query()
            ->where('role', UserRole::MunicipalViewer)
            ->where('is_active', true)
            ->whereNotNull('municipality_id')
            ->with('municipality')
            ->get()
            ->filter(function (User $user) use ($agenda): bool {
                return $user->municipality !== null
                    && MunicipalRequestAccess::agendaBelongsToMunicipality($agenda, $user->municipality);
            })
            ->values();
    }
}

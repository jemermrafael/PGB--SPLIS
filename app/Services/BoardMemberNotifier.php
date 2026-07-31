<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\CommitteeMembership;
use App\Models\CommitteeTerm;
use App\Models\LegislativeSession;
use App\Models\ObDocument;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\AgendaDeadline;
use App\Support\CommitteeLookup;
use Illuminate\Support\Collection;

class BoardMemberNotifier
{
    public function __construct(
        protected EmailNotificationService $emails,
    ) {}

    public function notifyCommitteeReferral(AgendaItem $agenda): void
    {
        $referral = trim((string) ($agenda->committee_referred ?? ''));

        if ($referral === '') {
            return;
        }

        $committee = CommitteeLookup::findByName($referral);

        if ($committee === null) {
            return;
        }

        $label = $agenda->displayLabel();
        $body = sprintf('%s was referred to %s.', $label, $committee->name);

        foreach ($this->usersForAgendaCommittee($agenda) as $user) {
            $notification = UserNotification::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'agenda_item_id' => $agenda->id,
                    'type' => UserNotification::TYPE_COMMITTEE_REFERRAL,
                ],
                [
                    'title' => 'Agenda referred to your committee',
                    'body' => $body,
                    'link' => route('agenda.show', $agenda, absolute: false),
                ],
            );

            $this->emails->sendForNotification(
                $user,
                $notification,
                EmailNotificationSettings::AUDIENCE_BOARD_MEMBER,
                vars: [
                    'label' => $label,
                    'committee' => $committee->name,
                ],
            );
        }
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
        $body = sprintf('%s was published to %s.', $label, $target);
        $number = trim((string) ($agenda->reso_ord_ao_no ?? ''));
        $series = (int) ($agenda->reso_ord_ao_series ?? 0);
        $numberSuffix = '';
        if ($number !== '' && $series > 0) {
            $numberSuffix = ' ('.$number.' s. '.$series.')';
        } elseif ($number !== '') {
            $numberSuffix = ' ('.$number.')';
        }
        $emailType = $this->emails->resolvePublishedEmailType(
            EmailNotificationSettings::AUDIENCE_BOARD_MEMBER,
            $target,
        );

        foreach ($this->usersForAgendaCommittee($agenda) as $user) {
            $notification = UserNotification::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'agenda_item_id' => $agenda->id,
                    'type' => UserNotification::TYPE_AGENDA_PUBLISHED,
                ],
                [
                    'title' => 'Agenda published',
                    'body' => $body,
                    'link' => $agenda->publishedTargetRoute() ?? route('agenda.show', $agenda, absolute: false),
                ],
            );

            $this->emails->sendForNotification(
                $user,
                $notification,
                EmailNotificationSettings::AUDIENCE_BOARD_MEMBER,
                vars: [
                    'label' => $label,
                    'target' => $target,
                    'number_suffix' => $numberSuffix,
                ],
                emailType: $emailType,
            );
        }
    }

    public function notifyAgendaAddedToOb(AgendaItem $agenda, LegislativeSession $session, bool $reNotify = false): void
    {
        $this->notifyAgendasAddedToOb([$agenda], $session, $reNotify);
    }

    /**
     * One in-app notification + email per board member for agendas in this batch.
     * Later batches for the same session create a new notification (not merged).
     *
     * @param  iterable<int, AgendaItem>  $agendas
     */
    public function notifyAgendasAddedToOb(iterable $agendas, LegislativeSession $session, bool $reNotify = false): void
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

        $sessionTitle = $session->displayTitle();
        $byUser = [];

        foreach ($agendas as $agenda) {
            foreach ($this->usersForAgendaCommittee($agenda) as $user) {
                $byUser[$user->id] ??= ['user' => $user, 'agendas' => collect()];
                $byUser[$user->id]['agendas']->put($agenda->id, $agenda);
            }
        }

        foreach ($byUser as $entry) {
            /** @var User $user */
            $user = $entry['user'];
            /** @var Collection<int, AgendaItem> $userAgendas */
            $userAgendas = $entry['agendas']
                ->sortBy(fn (AgendaItem $agenda) => [(int) preg_replace('/\D+/', '', (string) $agenda->tracking_no), $agenda->id])
                ->values();
            $labels = $userAgendas
                ->map(fn (AgendaItem $agenda) => $agenda->displayLabel())
                ->filter()
                ->values()
                ->all();

            if ($labels === []) {
                continue;
            }

            $title = 'Agenda added to Order of Business';
            $labelList = implode(', ', $labels);
            $summary = sprintf('%s was added to %s.', $labelList, $sessionTitle);

            if ($reNotify) {
                UserNotification::query()
                    ->where('user_id', $user->id)
                    ->where('legislative_session_id', $session->id)
                    ->where('type', UserNotification::TYPE_AGENDA_ADDED_TO_OB)
                    ->delete();
            } else {
                $alreadyNotified = UserNotification::query()
                    ->where('user_id', $user->id)
                    ->where('legislative_session_id', $session->id)
                    ->where('type', UserNotification::TYPE_AGENDA_ADDED_TO_OB)
                    ->where('body', $summary)
                    ->exists();

                if ($alreadyNotified) {
                    continue;
                }
            }

            $notification = UserNotification::query()->create([
                'user_id' => $user->id,
                'legislative_session_id' => $session->id,
                'type' => UserNotification::TYPE_AGENDA_ADDED_TO_OB,
                'agenda_item_id' => null,
                'title' => $title,
                'body' => $summary,
                'link' => route('ob.sessions.show', $session, absolute: false),
                'read_at' => null,
            ]);

            $this->emails->sendForNotification(
                $user,
                $notification,
                EmailNotificationSettings::AUDIENCE_BOARD_MEMBER,
                force: true,
                vars: [
                    'label' => $labelList,
                    'summary' => $summary,
                    'session' => $sessionTitle,
                    'email_subject' => $title,
                ],
            );
        }
    }

    public function notifySessionCreated(LegislativeSession $session): void
    {
        if (! $session->isNotifiableToBoardMembers()) {
            return;
        }

        $sessionTitle = $session->displayTitle();

        foreach ($this->allBoardMemberUsers() as $user) {
            $notification = UserNotification::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'legislative_session_id' => $session->id,
                    'type' => UserNotification::TYPE_SESSION_CREATED,
                ],
                [
                    'title' => 'New Session scheduled',
                    'body' => $sessionTitle,
                    'link' => route('ob.sessions.show', $session, absolute: false),
                ],
            );

            $this->emails->sendForNotification(
                $user,
                $notification,
                EmailNotificationSettings::AUDIENCE_BOARD_MEMBER,
                vars: [
                    'session' => $sessionTitle,
                ],
            );
        }

        $this->emails->notifyStaff(
            UserNotification::TYPE_SESSION_CREATED,
            [
                'session' => $sessionTitle,
                'title' => 'New Session scheduled',
                'body' => $sessionTitle,
            ],
            route('ob.sessions.show', $session),
        );
    }

    public function notifyObDocumentCreated(LegislativeSession $session, ObDocument $document): void
    {
        $session->setRelation('obDocument', $document);

        if (! $session->isNotifiableToBoardMembers()) {
            return;
        }

        foreach ($this->allBoardMemberUsers() as $user) {
            $notification = UserNotification::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'legislative_session_id' => $session->id,
                    'type' => UserNotification::TYPE_OB_DOCUMENT_CREATED,
                ],
                [
                    'title' => 'Order of Business created',
                    'body' => $document->title,
                    'link' => route('ob.sessions.show', $session, absolute: false),
                ],
            );

            $this->emails->sendForNotification(
                $user,
                $notification,
                EmailNotificationSettings::AUDIENCE_BOARD_MEMBER,
                vars: [
                    'document_title' => (string) $document->title,
                    'session' => $session->displayTitle(),
                ],
            );
        }

        $this->emails->notifyStaff(
            UserNotification::TYPE_OB_DOCUMENT_CREATED,
            [
                'document_title' => (string) $document->title,
                'session' => $session->displayTitle(),
                'title' => 'Order of Business created',
                'body' => (string) $document->title,
            ],
            route('ob.sessions.show', $session),
        );
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

        foreach ($this->usersForAgendaCommittee($agenda) as $user) {
            $notification = UserNotification::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'agenda_item_id' => $agenda->id,
                    'type' => UserNotification::TYPE_AGENDA_EXPIRING_SOON,
                ],
                [
                    'title' => 'Agenda deadline approaching',
                    'body' => $body,
                    'link' => route('agenda.show', $agenda, absolute: false),
                ],
            );

            $this->emails->sendForNotification(
                $user,
                $notification,
                EmailNotificationSettings::AUDIENCE_BOARD_MEMBER,
                vars: [
                    'label' => $label,
                    'due_date' => $dueDate,
                    'days_left_suffix' => $daysLeftSuffix,
                ],
            );
        }

        $this->emails->notifyStaff(
            UserNotification::TYPE_AGENDA_EXPIRING_SOON,
            [
                'label' => $label,
                'due_date' => $dueDate,
                'days_left_suffix' => $daysLeftSuffix,
                'title' => 'Agenda deadline approaching',
                'body' => $body,
            ],
            route('agenda.show', $agenda),
        );
    }

    /** @return Collection<int, User> */
    protected function usersForAgendaCommittee(AgendaItem $agenda): Collection
    {
        $referral = trim((string) ($agenda->committee_referred ?? ''));

        if ($referral === '') {
            return collect();
        }

        $committee = CommitteeLookup::findByName($referral);

        if ($committee === null) {
            return collect();
        }

        $termId = CommitteeTerm::query()->current()->value('id');

        $memberIds = CommitteeMembership::query()
            ->where('committee_id', $committee->id)
            ->when($termId, fn ($query) => $query->where('committee_term_id', $termId))
            ->pluck('board_member_id')
            ->unique()
            ->all();

        if ($memberIds === []) {
            return collect();
        }

        return User::query()
            ->whereIn('board_member_id', $memberIds)
            ->where('is_active', true)
            ->get();
    }

    /** @return Collection<int, User> */
    protected function allBoardMemberUsers(): Collection
    {
        return User::query()
            ->where('role', UserRole::BoardMember)
            ->where('is_active', true)
            ->whereNotNull('board_member_id')
            ->get();
    }
}

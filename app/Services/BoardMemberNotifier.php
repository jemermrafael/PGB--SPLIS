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
        $label = $agenda->displayLabel();
        $sessionTitle = $session->displayTitle();
        $body = sprintf('%s was added to %s.', $label, $sessionTitle);

        foreach ($this->usersForAgendaCommittee($agenda) as $user) {
            $attributes = [
                'title' => 'Agenda added to Order of Business',
                'body' => $body,
                'link' => route('ob.sessions.show', $session, absolute: false),
            ];

            if ($reNotify) {
                $attributes['read_at'] = null;
            }

            $notification = UserNotification::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'agenda_item_id' => $agenda->id,
                    'legislative_session_id' => $session->id,
                    'type' => UserNotification::TYPE_AGENDA_ADDED_TO_OB,
                ],
                $attributes,
            );

            $this->emails->sendForNotification(
                $user,
                $notification,
                EmailNotificationSettings::AUDIENCE_BOARD_MEMBER,
                force: $reNotify,
                vars: [
                    'label' => $label,
                    'session' => $sessionTitle,
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

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

        $body = sprintf(
            '%s was referred to %s.',
            $agenda->displayLabel(),
            $committee->name,
        );

        foreach ($this->usersForAgendaCommittee($agenda) as $user) {
            UserNotification::query()->firstOrCreate(
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

        $body = sprintf(
            '%s was published to %s.',
            $agenda->displayLabel(),
            $target,
        );

        foreach ($this->usersForAgendaCommittee($agenda) as $user) {
            UserNotification::query()->firstOrCreate(
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
        }
    }

    public function notifyAgendaAddedToOb(AgendaItem $agenda, LegislativeSession $session, bool $reNotify = false): void
    {
        $body = sprintf(
            '%s was added to %s.',
            $agenda->displayLabel(),
            $session->displayTitle(),
        );

        foreach ($this->usersForAgendaCommittee($agenda) as $user) {
            $attributes = [
                'title' => 'Agenda added to Order of Business',
                'body' => $body,
                'link' => route('ob.sessions.show', $session, absolute: false),
            ];

            if ($reNotify) {
                $attributes['read_at'] = null;
            }

            UserNotification::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'agenda_item_id' => $agenda->id,
                    'legislative_session_id' => $session->id,
                    'type' => UserNotification::TYPE_AGENDA_ADDED_TO_OB,
                ],
                $attributes,
            );
        }
    }

    public function notifySessionCreated(LegislativeSession $session): void
    {
        $body = $session->displayTitle();

        foreach ($this->allBoardMemberUsers() as $user) {
            UserNotification::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'legislative_session_id' => $session->id,
                    'type' => UserNotification::TYPE_SESSION_CREATED,
                ],
                [
                    'title' => 'New session scheduled',
                    'body' => $body,
                    'link' => route('ob.sessions.show', $session, absolute: false),
                ],
            );
        }
    }

    public function notifyObDocumentCreated(LegislativeSession $session, ObDocument $document): void
    {
        foreach ($this->allBoardMemberUsers() as $user) {
            UserNotification::query()->firstOrCreate(
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
        }
    }

    public function notifyAgendaExpiringSoon(AgendaItem $agenda): void
    {
        if (! AgendaDeadline::isWithinExpiringSoonWindow($agenda->due_date, $agenda->status)) {
            return;
        }

        $daysLeft = is_numeric($agenda->days_left_label) ? (int) $agenda->days_left_label : null;
        $suffix = $daysLeft === null
            ? ''
            : ' ('.$daysLeft.' day'.($daysLeft === 1 ? '' : 's').' left)';

        $body = sprintf(
            '%s is due on %s%s.',
            $agenda->displayLabel(),
            $agenda->due_date?->format('F j, Y'),
            $suffix,
        );

        foreach ($this->usersForAgendaCommittee($agenda) as $user) {
            UserNotification::query()->firstOrCreate(
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
        }
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

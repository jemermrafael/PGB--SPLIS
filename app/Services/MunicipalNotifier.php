<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\LegislativeSession;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\AgendaDeadline;
use App\Support\MunicipalRequestAccess;
use Illuminate\Support\Collection;

class MunicipalNotifier
{
    public function notifyCommitteeReferral(AgendaItem $agenda): void
    {
        $referral = trim((string) ($agenda->committee_referred ?? ''));

        if ($referral === '') {
            return;
        }

        $body = sprintf(
            '%s was referred to %s.',
            $agenda->displayLabel(),
            $referral,
        );

        foreach ($this->usersForAgenda($agenda) as $user) {
            UserNotification::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'agenda_item_id' => $agenda->id,
                    'type' => UserNotification::TYPE_COMMITTEE_REFERRAL,
                ],
                [
                    'title' => 'Your request was referred to a committee',
                    'body' => $body,
                    'link' => route('municipal.requests.show', $agenda, absolute: false),
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

        foreach ($this->usersForAgenda($agenda) as $user) {
            UserNotification::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'agenda_item_id' => $agenda->id,
                    'type' => UserNotification::TYPE_AGENDA_PUBLISHED,
                ],
                [
                    'title' => 'Your request was published',
                    'body' => $body,
                    'link' => route('municipal.requests.show', $agenda, absolute: false),
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

        foreach ($this->usersForAgenda($agenda) as $user) {
            $attributes = [
                'title' => 'Your request was added to the Order of Business',
                'body' => $body,
                'link' => route('municipal.requests.show', $agenda, absolute: false),
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

        foreach ($this->usersForAgenda($agenda) as $user) {
            UserNotification::query()->firstOrCreate(
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

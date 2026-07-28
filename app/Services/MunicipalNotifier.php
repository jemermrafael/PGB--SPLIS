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
    public function __construct(
        protected EmailNotificationService $emails,
    ) {}

    public function notifyCommitteeReferral(AgendaItem $agenda): void
    {
        $referral = trim((string) ($agenda->committee_referred ?? ''));

        if ($referral === '') {
            return;
        }

        $label = $agenda->displayLabel();
        $body = sprintf('%s was referred to %s.', $label, $referral);

        foreach ($this->usersForAgenda($agenda) as $user) {
            $notification = UserNotification::query()->firstOrCreate(
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

            $this->emails->sendForNotification(
                $user,
                $notification,
                EmailNotificationSettings::AUDIENCE_MUNICIPAL,
                vars: [
                    'label' => $label,
                    'committee' => $referral,
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
            EmailNotificationSettings::TYPE_RESOLUTION_PUBLISHED => 'Agenda was published to resolution',
            EmailNotificationSettings::TYPE_APPROPRIATION_ORDINANCE_PUBLISHED => 'New appropriation ordinance published',
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

    public function notifyAgendaAddedToOb(AgendaItem $agenda, LegislativeSession $session, bool $reNotify = false): void
    {
        $label = $agenda->displayLabel();
        $sessionTitle = $session->displayTitle();
        $body = sprintf('%s was added to %s.', $label, $sessionTitle);

        foreach ($this->usersForAgenda($agenda) as $user) {
            $attributes = [
                'title' => 'Your request was added to the Order of Business',
                'body' => $body,
                'link' => route('municipal.requests.show', $agenda, absolute: false),
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
                EmailNotificationSettings::AUDIENCE_MUNICIPAL,
                force: $reNotify,
                vars: [
                    'label' => $label,
                    'session' => $sessionTitle,
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

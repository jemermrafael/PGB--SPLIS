<?php

namespace App\Support;

use App\Models\AgendaItem;
use App\Models\LegislativeSession;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Collection;

class ObAgendaAddedDigest
{
    /**
     * Drop agendas already mentioned in a prior agenda-added-to-OB digest for this user/session.
     *
     * @param  Collection<int, AgendaItem>  $agendas
     * @return Collection<int, AgendaItem>
     */
    public static function filterUnnotified(User $user, LegislativeSession $session, Collection $agendas): Collection
    {
        $bodies = UserNotification::query()
            ->where('user_id', $user->id)
            ->where('legislative_session_id', $session->id)
            ->where('type', UserNotification::TYPE_AGENDA_ADDED_TO_OB)
            ->pluck('body')
            ->filter(fn ($body) => is_string($body) && $body !== '')
            ->values()
            ->all();

        if ($bodies === []) {
            return $agendas->values();
        }

        return $agendas
            ->filter(function (AgendaItem $agenda) use ($bodies): bool {
                $label = trim($agenda->displayLabel());

                if ($label === '') {
                    return true;
                }

                foreach ($bodies as $body) {
                    if (self::bodyMentionsLabel($body, $label)) {
                        return false;
                    }
                }

                return true;
            })
            ->values();
    }

    /**
     * Bodies may look like:
     * - "#349, #350 was added to …"
     * - "#349 — A. Unfinished Business was added to …"
     * - "• #349 — IV. Committee Reports (with committee report)"
     */
    public static function bodyMentionsLabel(string $body, string $label): bool
    {
        $quoted = preg_quote($label, '/');

        return (bool) preg_match(
            '/(?:^|[\n,•\s])'.$quoted.'(?![0-9A-Za-z])/u',
            $body,
        );
    }
}

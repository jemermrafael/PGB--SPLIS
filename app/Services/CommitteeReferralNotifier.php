<?php

namespace App\Services;

use App\Models\AgendaItem;
use App\Models\Committee;
use App\Models\CommitteeMembership;
use App\Models\CommitteeTerm;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\CommitteeLookup;

class CommitteeReferralNotifier
{
    public function notifyForAgenda(AgendaItem $agenda): void
    {
        $referral = trim((string) ($agenda->committee_referred ?? ''));

        if ($referral === '') {
            return;
        }

        $committee = CommitteeLookup::findByName($referral);

        if ($committee === null) {
            return;
        }

        $termId = CommitteeTerm::query()->current()->value('id');

        $memberIds = CommitteeMembership::query()
            ->where('committee_id', $committee->id)
            ->when($termId, fn ($query) => $query->where('committee_term_id', $termId))
            ->pluck('board_member_id')
            ->unique()
            ->all();

        if ($memberIds === []) {
            return;
        }

        $users = User::query()
            ->whereIn('board_member_id', $memberIds)
            ->where('is_active', true)
            ->get();

        foreach ($users as $user) {
            $exists = UserNotification::query()
                ->where('user_id', $user->id)
                ->where('agenda_item_id', $agenda->id)
                ->where('type', UserNotification::TYPE_COMMITTEE_REFERRAL)
                ->exists();

            if ($exists) {
                continue;
            }

            UserNotification::create([
                'user_id' => $user->id,
                'type' => UserNotification::TYPE_COMMITTEE_REFERRAL,
                'title' => 'Agenda referred to your committee',
                'body' => sprintf(
                    '%s was referred to %s.',
                    $agenda->displayLabel(),
                    $committee->name,
                ),
                'link' => route('agenda.show', $agenda),
                'agenda_item_id' => $agenda->id,
            ]);
        }
    }
}

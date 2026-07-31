<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\BoardMemberWatchlistItem;
use App\Models\Ordinance;
use App\Models\Resolution;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class BoardMemberWatchlistService
{
    public function __construct(
        protected EmailNotificationService $emails,
        protected UserNotificationPreferenceService $preferences,
    ) {}

    /**
     * @return Collection<int, BoardMemberWatchlistItem>
     */
    public function listForUser(User $user): Collection
    {
        return BoardMemberWatchlistItem::query()
            ->where('user_id', $user->id)
            ->with('watchable')
            ->latest('id')
            ->get();
    }

    public function isWatching(User $user, Model $watchable): bool
    {
        $this->ensureSupported($watchable);

        return BoardMemberWatchlistItem::query()
            ->where('user_id', $user->id)
            ->where('watchable_type', $watchable::class)
            ->where('watchable_id', $watchable->getKey())
            ->exists();
    }

    public function toggle(User $user, Model $watchable): bool
    {
        $this->ensureSupported($watchable);

        $existing = BoardMemberWatchlistItem::query()
            ->where('user_id', $user->id)
            ->where('watchable_type', $watchable::class)
            ->where('watchable_id', $watchable->getKey())
            ->first();

        if ($existing) {
            $existing->delete();

            return false;
        }

        BoardMemberWatchlistItem::query()->create([
            'user_id' => $user->id,
            'watchable_type' => $watchable::class,
            'watchable_id' => $watchable->getKey(),
        ]);

        return true;
    }

    public function notifyWatchersOnAgendaPublished(AgendaItem $agenda): void
    {
        $agenda->loadMissing(['resolution', 'ordinance']);

        $watchers = BoardMemberWatchlistItem::query()
            ->where(function ($query) use ($agenda): void {
                $query->where(function ($agendaWatch) use ($agenda): void {
                    $agendaWatch
                        ->where('watchable_type', AgendaItem::class)
                        ->where('watchable_id', $agenda->id);
                });

                if ($agenda->resolution_id) {
                    $query->orWhere(function ($resolutionWatch) use ($agenda): void {
                        $resolutionWatch
                            ->where('watchable_type', Resolution::class)
                            ->where('watchable_id', $agenda->resolution_id);
                    });
                }

                if ($agenda->ordinance_id) {
                    $query->orWhere(function ($ordinanceWatch) use ($agenda): void {
                        $ordinanceWatch
                            ->where('watchable_type', Ordinance::class)
                            ->where('watchable_id', $agenda->ordinance_id);
                    });
                }
            })
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter(fn ($user) => $user instanceof User)
            ->filter(fn (User $user) => $user->role === UserRole::BoardMember && $user->is_active)
            ->unique('id')
            ->values();

        if ($watchers->isEmpty()) {
            return;
        }

        $label = $agenda->displayLabel();
        $target = $agenda->publishedTargetLabel() ?? 'published item';
        $body = sprintf('%s was published to %s.', $label, $target);
        $link = $agenda->publishedTargetRoute() ?? route('agenda.show', $agenda, absolute: false);

        foreach ($watchers as $user) {
            $notification = null;
            if ($this->preferences->allowsInApp($user, UserNotification::TYPE_WATCHLIST_PUBLISHED)) {
                $notification = UserNotification::query()->firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'agenda_item_id' => $agenda->id,
                        'type' => UserNotification::TYPE_WATCHLIST_PUBLISHED,
                    ],
                    [
                        'title' => 'Watched item published',
                        'body' => $body,
                        'link' => $link,
                    ],
                );
            }

            if ($notification) {
                $this->emails->sendForNotification(
                    $user,
                    $notification,
                    EmailNotificationSettings::AUDIENCE_BOARD_MEMBER,
                    vars: [
                        'label' => $label,
                        'target' => $target,
                        'title' => 'Watched item published',
                        'body' => $body,
                    ],
                );

                continue;
            }

            $this->emails->sendTemplated(
                $user,
                EmailNotificationSettings::AUDIENCE_BOARD_MEMBER,
                UserNotification::TYPE_WATCHLIST_PUBLISHED,
                [
                    'label' => $label,
                    'target' => $target,
                    'title' => 'Watched item published',
                    'body' => $body,
                ],
                url($link),
            );
        }
    }

    protected function ensureSupported(Model $watchable): void
    {
        abort_unless(
            $watchable instanceof AgendaItem
                || $watchable instanceof Resolution
                || $watchable instanceof Ordinance,
            422,
            'Unsupported watchlist item type.',
        );
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserNotificationController extends Controller
{
    private const DEFAULT_LIMIT = 10;

    public function feed(Request $request): View
    {
        $user = $this->authorizedUser($request);
        $page = $this->paginatedNotifications($user, self::DEFAULT_LIMIT);

        return view('notifications.feed', [
            'notifications' => $page['notifications'],
            'hasMore' => $page['has_more'],
            'nextBeforeId' => $page['next_before_id'],
            'unreadCount' => $this->unreadCount($user),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->authorizedUser($request);
        $limit = min(max((int) $request->input('limit', self::DEFAULT_LIMIT), 1), 30);
        $beforeId = $request->filled('before_id') ? (int) $request->input('before_id') : null;
        $page = $this->paginatedNotifications($user, $limit, $beforeId);

        return response()->json([
            'notifications' => $page['notifications'],
            'has_more' => $page['has_more'],
            'next_before_id' => $page['next_before_id'],
            'count' => $this->unreadCount($user),
        ]);
    }

    public function markRead(Request $request, UserNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        abort_unless($this->ownsNotificationType($request->user(), $notification), 403);

        $notification->markRead();

        return response()->json([
            'ok' => true,
            'count' => $this->unreadCount($request->user()),
            'notification' => [
                'id' => $notification->id,
                'unread' => false,
            ],
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $user = $this->authorizedUser($request);

        $this->notificationsQuery($user)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'ok' => true,
            'count' => 0,
        ]);
    }

    private function authorizedUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User && ($user->isBoardMember() || $user->canAdmin()), 403);

        return $user;
    }

    /**
     * @return array{
     *     notifications: \Illuminate\Support\Collection<int, array<string, mixed>>,
     *     has_more: bool,
     *     next_before_id: int|null
     * }
     */
    private function paginatedNotifications(User $user, int $limit, ?int $beforeId = null): array
    {
        $query = $this->notificationsQuery($user)
            ->latest('created_at')
            ->latest('id');

        if ($beforeId !== null) {
            $query->where('id', '<', $beforeId);
        }

        $rows = $query->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;

        if ($hasMore) {
            $rows = $rows->take($limit);
        }

        return [
            'notifications' => $rows->map(fn (UserNotification $notification) => $this->notificationPayload($notification))->values(),
            'has_more' => $hasMore,
            'next_before_id' => $hasMore ? $rows->last()?->id : null,
        ];
    }

    /** @return Builder<UserNotification> */
    private function notificationsQuery(User $user): Builder
    {
        $query = UserNotification::query()->where('user_id', $user->id);

        if ($user->canAdmin()) {
            return $query->where('type', UserNotification::TYPE_ACTIVITY_LOG);
        }

        return $query->where('type', '!=', UserNotification::TYPE_ACTIVITY_LOG);
    }

    private function unreadCount(User $user): int
    {
        return $this->notificationsQuery($user)->whereNull('read_at')->count();
    }

    private function ownsNotificationType(User $user, UserNotification $notification): bool
    {
        if ($user->canAdmin()) {
            return $notification->type === UserNotification::TYPE_ACTIVITY_LOG;
        }

        return $notification->type !== UserNotification::TYPE_ACTIVITY_LOG;
    }

    /** @return array<string, mixed> */
    public function notificationPayload(UserNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'title' => $notification->title,
            'body' => $notification->body,
            'link' => $notification->link,
            'link_label' => $this->linkLabel($notification),
            'unread' => $notification->isUnread(),
            'created_at' => $notification->created_at?->diffForHumans(),
        ];
    }

    private function linkLabel(UserNotification $notification): string
    {
        return match ($notification->type) {
            UserNotification::TYPE_COMMITTEE_REFERRAL,
            UserNotification::TYPE_AGENDA_PUBLISHED,
            UserNotification::TYPE_AGENDA_ADDED_TO_OB,
            UserNotification::TYPE_AGENDA_EXPIRING_SOON => str_contains((string) $notification->link, '/agenda/')
                ? 'View agenda'
                : match (true) {
                    str_contains((string) $notification->link, '/resolutions/') => 'View resolution',
                    str_contains((string) $notification->link, '/ordinances/') => 'View ordinance',
                    str_contains((string) $notification->link, '/appropriation-ordinances/') => 'View ordinance',
                    default => 'View details',
                },
            UserNotification::TYPE_SESSION_CREATED,
            UserNotification::TYPE_OB_DOCUMENT_CREATED => 'View session',
            default => match (true) {
                str_contains((string) $notification->link, '/agenda/') => 'View agenda',
                str_contains((string) $notification->link, '/resolutions/') => 'View resolution',
                str_contains((string) $notification->link, '/incoming/') => 'View incoming',
                str_contains((string) $notification->link, '/ordinances/') => 'View ordinance',
                str_contains((string) $notification->link, '/order-of-business/') => 'View session',
                str_contains((string) $notification->link, '/references/') => 'View reference',
                default => 'View details',
            },
        };
    }
}

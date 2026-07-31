<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserNotification;

class UserNotificationPreferenceService
{
    /**
     * @return list<string>
     */
    public function preferenceTypes(User $user): array
    {
        if (! $user->isBoardMember()) {
            return [];
        }

        return [
            UserNotification::TYPE_COMMITTEE_REFERRAL,
            UserNotification::TYPE_AGENDA_PUBLISHED,
            UserNotification::TYPE_AGENDA_ADDED_TO_OB,
            UserNotification::TYPE_SESSION_CREATED,
            UserNotification::TYPE_OB_DOCUMENT_CREATED,
            UserNotification::TYPE_AGENDA_EXPIRING_SOON,
            UserNotification::TYPE_WATCHLIST_PUBLISHED,
        ];
    }

    /**
     * @return array{
     *     in_app: array<string, bool>,
     *     email: array<string, bool>
     * }
     */
    public function defaultsFor(User $user): array
    {
        $types = $this->preferenceTypes($user);

        return [
            'in_app' => collect($types)->mapWithKeys(fn (string $type) => [$type => true])->all(),
            'email' => collect($types)->mapWithKeys(fn (string $type) => [$type => true])->all(),
        ];
    }

    public function allowsInApp(User $user, string $type): bool
    {
        $normalized = $this->normalizeType($type);

        return (bool) ($this->resolved($user)['in_app'][$normalized] ?? true);
    }

    public function allowsEmail(User $user, string $type): bool
    {
        $normalized = $this->normalizeType($type);

        return (bool) ($this->resolved($user)['email'][$normalized] ?? true);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function update(User $user, array $values): void
    {
        $resolved = $this->resolved($user);
        $types = $this->preferenceTypes($user);

        foreach (['in_app', 'email'] as $channel) {
            foreach ($types as $type) {
                if (array_key_exists($type, $values[$channel] ?? [])) {
                    $resolved[$channel][$type] = (bool) $values[$channel][$type];
                } else {
                    $resolved[$channel][$type] = false;
                }
            }
        }

        $user->forceFill(['notification_preferences' => $resolved])->save();
    }

    /**
     * @return array{
     *     in_app: array<string, bool>,
     *     email: array<string, bool>
     * }
     */
    public function resolved(User $user): array
    {
        $defaults = $this->defaultsFor($user);
        $stored = is_array($user->notification_preferences) ? $user->notification_preferences : [];

        foreach (['in_app', 'email'] as $channel) {
            foreach (array_keys($defaults[$channel]) as $type) {
                if (array_key_exists($type, $stored[$channel] ?? [])) {
                    $defaults[$channel][$type] = (bool) $stored[$channel][$type];
                }
            }
        }

        return $defaults;
    }

    protected function normalizeType(string $type): string
    {
        return match ($type) {
            EmailNotificationSettings::TYPE_RESOLUTION_PUBLISHED,
            EmailNotificationSettings::TYPE_ORDINANCE_PUBLISHED,
            EmailNotificationSettings::TYPE_APPROPRIATION_ORDINANCE_PUBLISHED => UserNotification::TYPE_AGENDA_PUBLISHED,
            default => $type,
        };
    }
}

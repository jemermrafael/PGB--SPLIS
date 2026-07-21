<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    public const TYPE_COMMITTEE_REFERRAL = 'committee_referral';
    public const TYPE_ACTIVITY_LOG = 'activity_log';
    public const TYPE_AGENDA_PUBLISHED = 'agenda_published';
    public const TYPE_AGENDA_ADDED_TO_OB = 'agenda_added_to_ob';
    public const TYPE_SESSION_CREATED = 'session_created';
    public const TYPE_OB_DOCUMENT_CREATED = 'ob_document_created';
    public const TYPE_AGENDA_EXPIRING_SOON = 'agenda_expiring_soon';

    public const RETENTION_DAYS = 30;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'link',
        'agenda_item_id',
        'activity_log_id',
        'legislative_session_id',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function agendaItem(): BelongsTo
    {
        return $this->belongsTo(AgendaItem::class);
    }

    public function activityLog(): BelongsTo
    {
        return $this->belongsTo(ActivityLog::class);
    }

    public function legislativeSession(): BelongsTo
    {
        return $this->belongsTo(LegislativeSession::class);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeWithinRetention($query)
    {
        return $query->where('created_at', '>=', now()->subDays(self::RETENTION_DAYS));
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleToRecipient(Builder $query, User $user): Builder
    {
        if (! $user->isBoardMember()) {
            return $query;
        }

        return $query->where(function (Builder $notification): void {
            $notification
                ->where('type', '!=', self::TYPE_SESSION_CREATED)
                ->orWhere(function (Builder $sessionCreated): void {
                    $sessionCreated
                        ->where('type', self::TYPE_SESSION_CREATED)
                        ->whereHas('legislativeSession', fn (Builder $session) => $session->notifiableToBoardMembers());
                });
        });
    }

    public static function pruneExpired(): int
    {
        return self::query()
            ->where('created_at', '<', now()->subDays(self::RETENTION_DAYS))
            ->delete();
    }

    /** @return list<string> */
    public static function boardMemberTypes(): array
    {
        return [
            self::TYPE_COMMITTEE_REFERRAL,
            self::TYPE_AGENDA_PUBLISHED,
            self::TYPE_AGENDA_ADDED_TO_OB,
            self::TYPE_SESSION_CREATED,
            self::TYPE_OB_DOCUMENT_CREATED,
            self::TYPE_AGENDA_EXPIRING_SOON,
        ];
    }

    /** @return list<string> */
    public static function municipalTypes(): array
    {
        return [
            self::TYPE_COMMITTEE_REFERRAL,
            self::TYPE_AGENDA_PUBLISHED,
            self::TYPE_AGENDA_ADDED_TO_OB,
            self::TYPE_AGENDA_EXPIRING_SOON,
        ];
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    public function markRead(): void
    {
        if ($this->read_at === null) {
            $this->forceFill(['read_at' => now()])->save();
        }
    }
}

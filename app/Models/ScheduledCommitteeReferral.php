<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScheduledCommitteeReferral extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'legislative_session_id',
        'scheduled_at',
        'status',
        'sent_at',
        'created_by',
        'notes',
        'send_email',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'send_email' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function legislativeSession(): BelongsTo
    {
        return $this->belongsTo(LegislativeSession::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(CommitteeReferralDelivery::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function trashLabel(): string
    {
        $session = $this->legislativeSession;
        $when = $this->scheduled_at?->timezone(config('app.timezone'))->format('M j, Y g:i A');

        if ($session) {
            return trim($session->displayTitle().($when ? ' — '.$when : ''));
        }

        return 'Scheduled referral #'.$this->id.($when ? ' — '.$when : '');
    }
}

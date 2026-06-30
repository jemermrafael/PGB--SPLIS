<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SptrackImportQueue extends Model
{
    protected $table = 'sptrack_import_queue';

    public const CONFIDENCE_HIGH = 'high';

    public const CONFIDENCE_MEDIUM = 'medium';

    public const CONFIDENCE_LOW = 'low';

    public const CONFIDENCE_NONE = 'none';

    public const ACTION_ENRICH = 'enrich';

    public const ACTION_CREATE = 'create';

    public const ACTION_SKIP = 'skip';

    public const ACTION_REVIEW = 'review';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'batch_id',
        'legacy_file_id',
        'sp_res_no',
        'sp_series',
        'sp_sequence',
        'sp_title',
        'sp_date_approved',
        'mun_resolution_no',
        'mun_title',
        'mun_series',
        'date_received',
        'municipality',
        'referral',
        'keyword',
        'sptrack_status',
        'action_taken',
        'agenda',
        'concerned_agency',
        'remarks',
        'sp_pdf_url',
        'mun_pdf_url',
        'sp_rec_added',
        'sp_rec_modified',
        'suggested_resolution_id',
        'confidence',
        'match_signals',
        'proposed_action',
        'user_action',
        'user_resolution_id',
        'queue_status',
        'reviewed_by',
        'reviewed_at',
        'applied_by',
        'applied_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'sp_series' => 'integer',
            'sp_sequence' => 'integer',
            'sp_date_approved' => 'date',
            'date_received' => 'date',
            'sp_rec_added' => 'datetime',
            'sp_rec_modified' => 'datetime',
            'match_signals' => 'array',
            'reviewed_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function suggestedResolution(): BelongsTo
    {
        return $this->belongsTo(Resolution::class, 'suggested_resolution_id');
    }

    public function userResolution(): BelongsTo
    {
        return $this->belongsTo(Resolution::class, 'user_resolution_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function applier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function targetResolutionId(): ?int
    {
        return $this->user_resolution_id ?? $this->suggested_resolution_id;
    }

    public function effectiveAction(): string
    {
        return $this->user_action ?? $this->proposed_action;
    }

    public function isReadyToApply(): bool
    {
        if ($this->queue_status !== self::STATUS_APPROVED) {
            return false;
        }

        $action = $this->effectiveAction();

        if ($action === self::ACTION_ENRICH) {
            return $this->targetResolutionId() !== null;
        }

        return in_array($action, [self::ACTION_CREATE, self::ACTION_SKIP], true);
    }

    public function scopePending($query)
    {
        return $query->where('queue_status', self::STATUS_PENDING);
    }
}

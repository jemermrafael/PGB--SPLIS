<?php

namespace App\Models;

use App\Models\Concerns\HasActivityLogs;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class IncomingDocument extends Model
{
    use HasActivityLogs;
    use SoftDeletes;

    public const SOURCE_SPTRACK = 'sptrack';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_AGENDA = 'agenda';

    public const LINK_UNLINKED = 'unlinked';

    public const LINK_LINKED = 'linked';

    protected $fillable = [
        'legacy_file_id',
        'agenda_item_id',
        'source',
        'link_status',
        'resolution_id',
        'mun_resolution_no',
        'date_received',
        'mun_series',
        'municipality',
        'title',
        'action_taken',
        'referral',
        'agenda',
        'workflow_status',
        'sp_res_no',
        'sp_series',
        'sp_title',
        'sp_date_approved',
        'keyword',
        'concerned_agency',
        'remarks',
        'mun_pdf_url',
        'sp_pdf_url',
        'sp_rec_added',
        'sp_rec_modified',
        'sp_rec_added_by',
        'sp_rec_modified_by',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date_received' => 'date',
            'sp_date_approved' => 'date',
            'sp_series' => 'integer',
            'sp_rec_added' => 'datetime',
            'sp_rec_modified' => 'datetime',
        ];
    }

    public function resolution(): BelongsTo
    {
        return $this->belongsTo(Resolution::class);
    }

    public function agendaItem(): BelongsTo
    {
        return $this->belongsTo(AgendaItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isLinked(): bool
    {
        return $this->link_status === self::LINK_LINKED && $this->resolution_id !== null;
    }

    public function displayLabel(): string
    {
        if ($this->sp_res_no && $this->sp_series) {
            return trim($this->sp_res_no).' / '.$this->sp_series;
        }

        if ($this->mun_resolution_no) {
            return $this->mun_resolution_no;
        }

        return 'File #'.($this->legacy_file_id ?? $this->id);
    }

    public function previousInList(): ?self
    {
        return static::query()
            ->where('id', '>', $this->id)
            ->orderBy('id')
            ->first();
    }

    public function nextInList(): ?self
    {
        return static::query()
            ->where('id', '<', $this->id)
            ->orderByDesc('id')
            ->first();
    }

    public function scopeUnlinked($query)
    {
        return $query->where('link_status', self::LINK_UNLINKED)->whereNull('resolution_id');
    }

    /**
     * SPLIS audit entries for this incoming record (including publish-from-incoming).
     *
     * @return Collection<int, ActivityLog>
     */
    public function splisActivityLogs(): Collection
    {
        return ActivityLog::query()
            ->with('user')
            ->where(function ($query) {
                $query->where('subject_type', static::class)
                    ->where('subject_id', $this->id)
                    ->orWhere('properties->incoming_id', $this->id);
            })
            ->latest('created_at')
            ->get();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class IncomingDocument extends Model
{
    use SoftDeletes;

    public const SOURCE_SPTRACK = 'sptrack';

    public const SOURCE_MANUAL = 'manual';

    public const LINK_UNLINKED = 'unlinked';

    public const LINK_LINKED = 'linked';

    protected $fillable = [
        'legacy_file_id',
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
        'sp_rec_modified',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date_received' => 'date',
            'sp_date_approved' => 'date',
            'sp_series' => 'integer',
            'sp_rec_modified' => 'datetime',
        ];
    }

    public function resolution(): BelongsTo
    {
        return $this->belongsTo(Resolution::class);
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
}

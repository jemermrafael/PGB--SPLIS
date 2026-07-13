<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class LegislativeSession extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'session_date',
        'session_time',
        'session_number',
        'session_kind',
        'venue',
        'prior_session_id',
        'status',
        'notes',
        'pdf_summary_committee_reports',
        'pdf_committee_reports',
        'pdf_draft_journal',
        'pdf_draft_minutes',
        'pdf_final_journal',
        'pdf_final_minutes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
        ];
    }

    public function priorSession(): BelongsTo
    {
        return $this->belongsTo(self::class, 'prior_session_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function obDocument(): HasOne
    {
        return $this->hasOne(ObDocument::class);
    }

    public function followUpSessions(): HasMany
    {
        return $this->hasMany(self::class, 'prior_session_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(SessionAttendance::class, 'legislative_session_id');
    }

    protected static function booted(): void
    {
        static::deleting(function (self $session): void {
            $document = $session->obDocument()->with('blocks')->first();
            if (! $document) {
                return;
            }

            $document->blocks()->delete();
            $document->delete();
        });
    }

    public function displayTitle(): string
    {
        $parts = array_filter([
            $this->session_number,
            $this->session_date?->format('F j, Y'),
        ]);

        return $parts !== [] ? implode(' — ', $parts) : 'Legislative session #'.$this->id;
    }

    public function sessionKindLabel(): string
    {
        return config('order_of_business.session_kinds.'.$this->session_kind, $this->session_kind);
    }

    public function statusLabel(): string
    {
        return config('order_of_business.session_statuses.'.$this->status, $this->status);
    }

    public function formattedSessionTime(): ?string
    {
        if (! $this->session_time) {
            return null;
        }

        return \Illuminate\Support\Str::of($this->session_time)->substr(0, 5);
    }

    public function formattedSessionTimeForPrint(): ?string
    {
        if (! $this->session_time) {
            return null;
        }

        $formatted = \Carbon\Carbon::parse($this->session_time)->format('g:i A');

        return str_replace(['AM', 'PM'], ['A.M.', 'P.M.'], $formatted);
    }

    /**
     * @return list<array{field: string, label: string, url: ?string}>
     */
    public function sessionPdfLinkRows(): array
    {
        return collect(config('order_of_business.session_pdf_links', []))
            ->map(fn (string $label, string $field) => [
                'field' => $field,
                'label' => $label,
                'url' => filled($this->{$field}) ? $this->{$field} : null,
            ])
            ->values()
            ->all();
    }

    public function hasSessionPdfLinks(): bool
    {
        foreach (array_keys(config('order_of_business.session_pdf_links', [])) as $field) {
            if (filled($this->{$field})) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithFinalObDocument(Builder $query): Builder
    {
        return $query->whereHas('obDocument', fn (Builder $document) => $document->final());
    }

    /**
     * Sessions board members may browse (session must be scheduled).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleToBoardMembers(Builder $query): Builder
    {
        return $query->where('status', 'scheduled');
    }

    public function isVisibleToBoardMembers(): bool
    {
        return $this->status === 'scheduled';
    }
}

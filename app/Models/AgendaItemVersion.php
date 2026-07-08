<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgendaItemVersion extends Model
{
    protected $fillable = [
        'agenda_item_id',
        'version_no',
        'change_reason',
        'snapshot',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'version_no' => 'integer',
            'snapshot' => 'array',
        ];
    }

    public function agendaItem(): BelongsTo
    {
        return $this->belongsTo(AgendaItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function changeReasonLabel(): string
    {
        return config('agenda.version_reasons.'.$this->change_reason, ucfirst(str_replace('_', ' ', $this->change_reason)));
    }

    public function snapshotValue(string $key, mixed $default = null): mixed
    {
        return $this->snapshot[$key] ?? $default;
    }

    public function snapshotTitle(): ?string
    {
        $title = $this->snapshotValue('title');

        return is_string($title) && $title !== '' ? $title : null;
    }
}

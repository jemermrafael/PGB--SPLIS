<?php

namespace App\Models;

use App\Services\AgendaItemRequestFileService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgendaItemRequestFile extends Model
{
    protected $fillable = [
        'agenda_item_id',
        'relative_folder',
        'original_filename',
        'stored_path',
        'mime_type',
        'file_size',
        'sort_order',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'sort_order' => 'integer',
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

    public function folderLabel(): string
    {
        $folder = trim((string) $this->relative_folder);

        return $folder !== '' ? $folder : 'Root';
    }

    public function publicUrl(): ?string
    {
        return app(AgendaItemRequestFileService::class)->publicUrl($this);
    }

    public function viewerMode(): ?string
    {
        return app(AgendaItemRequestFileService::class)->viewerMode($this);
    }

    public function existsLocally(): bool
    {
        return app(AgendaItemRequestFileService::class)->exists($this);
    }
}

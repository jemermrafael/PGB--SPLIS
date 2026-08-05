<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class IconLibraryItem extends Model
{
    protected $fillable = [
        'name',
        'original_filename',
        'stored_path',
        'mime_type',
        'created_by',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<Committee, $this>
     */
    public function committees(): HasMany
    {
        return $this->hasMany(Committee::class, 'icon_library_id');
    }

    public function existsLocally(): bool
    {
        return filled($this->stored_path) && Storage::disk('local')->exists($this->stored_path);
    }

    public function publicUrl(): string
    {
        return route('icon-library.show', $this);
    }

    public function isSvg(): bool
    {
        $mime = strtolower((string) ($this->mime_type ?? ''));
        if (str_contains($mime, 'svg')) {
            return true;
        }

        return str_ends_with(strtolower((string) ($this->stored_path ?? '')), '.svg')
            || str_ends_with(strtolower((string) ($this->original_filename ?? '')), '.svg');
    }

    /**
     * Raster icons (PNG, etc.) should keep their file colors; SVGs stay mask-tintable.
     */
    public function preservesOriginalColors(): bool
    {
        return ! $this->isSvg();
    }
}

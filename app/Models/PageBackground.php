<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PageBackground extends Model
{
    protected $fillable = [
        'page_key',
        'background_type',
        'color',
        'image_path',
        'image_original_filename',
        'mime_type',
        'position',
        'attachment',
        'repeat',
        'size',
    ];

    public function hasImage(): bool
    {
        return filled($this->image_path) && Storage::disk('local')->exists($this->image_path);
    }

    public function imageUrl(): ?string
    {
        if (! $this->hasImage()) {
            return null;
        }

        return route('page-backgrounds.show', $this);
    }
}

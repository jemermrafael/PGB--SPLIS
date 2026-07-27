<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageIconOverride extends Model
{
    protected $fillable = [
        'page_key',
        'icon_library_id',
    ];

    /**
     * @return BelongsTo<IconLibraryItem, $this>
     */
    public function iconLibraryItem(): BelongsTo
    {
        return $this->belongsTo(IconLibraryItem::class, 'icon_library_id');
    }
}

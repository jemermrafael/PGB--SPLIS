<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Resolution extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'legacy_sp_id',
        'resolution_no',
        'resolution_title',
        'document_type',
        'pdf_path',
        'series',
        'department_id',
        'date_approved',
        'sponsored_by',
        'category_id',
        'category2_id',
        'category3_id',
        'category4_id',
        'keyword',
        'committee',
        'app_ord_no',
        'amount',
        'municipality_id',
        'province',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date_approved' => 'date',
            'province' => 'boolean',
            'series' => 'integer',
            'amount' => 'integer',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function category2(): BelongsTo
    {
        return $this->belongsTo(Category2::class);
    }

    public function category3(): BelongsTo
    {
        return $this->belongsTo(Category3::class);
    }

    public function category4(): BelongsTo
    {
        return $this->belongsTo(Category4::class);
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

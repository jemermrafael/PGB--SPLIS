<?php

namespace App\Models;

use App\Models\Concerns\HasActivityLogs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Resolution extends Model
{
    use HasActivityLogs;
    use SoftDeletes;

    protected $fillable = [
        'legacy_sp_id',
        'incoming_document_id',
        'legacy_file_id',
        'legacy_sp_res_no',
        'sp_sequence',
        'mun_resolution_no',
        'mun_title',
        'mun_series',
        'date_received',
        'action_taken',
        'agenda',
        'concerned_agency',
        'remarks',
        'sp_pdf_url',
        'mun_pdf_url',
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

    public function incomingDocument(): BelongsTo
    {
        return $this->belongsTo(IncomingDocument::class);
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
}

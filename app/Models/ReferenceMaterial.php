<?php

namespace App\Models;

use App\Models\Concerns\HasActivityLogs;
use App\Models\Concerns\NavigatesById;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReferenceMaterial extends Model
{
    use HasActivityLogs;
    use NavigatesById;
    use SoftDeletes;

    protected $fillable = [
        'title',
        'document_type',
        'reference_no',
        'issuing_office',
        'date_issued',
        'effective_date',
        'summary',
        'keywords',
        'version_no',
        'supersedes_reference_material_id',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'status',
        'archived_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'date_issued' => 'date',
            'effective_date' => 'date',
            'archived_at' => 'datetime',
            'file_size' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_reference_material_id');
    }

    public function supersededBy(): HasMany
    {
        return $this->hasMany(self::class, 'supersedes_reference_material_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ReferenceMaterialVersion::class)->latest('created_at');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(ReferenceMaterialVersion::class)->latestOfMany('created_at');
    }

    public function documentTypeLabel(): string
    {
        return config('reference_materials.document_types.'.$this->document_type, $this->document_type);
    }

    public function statusLabel(): string
    {
        return config('reference_materials.statuses.'.$this->status, $this->status);
    }

    public function hasFile(): bool
    {
        return filled($this->file_path);
    }

    public function isPdf(): bool
    {
        $mime = strtolower((string) ($this->mime_type ?? ''));
        $name = strtolower((string) ($this->original_filename ?? $this->file_path ?? ''));

        return str_contains($mime, 'pdf') || str_ends_with($name, '.pdf');
    }
}


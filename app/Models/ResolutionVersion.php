<?php

namespace App\Models;

use App\Services\PdfAttachmentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResolutionVersion extends Model
{
    protected $fillable = [
        'resolution_id',
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

    public function resolution(): BelongsTo
    {
        return $this->belongsTo(Resolution::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function changeReasonLabel(): string
    {
        return config('resolutions.version_reasons.'.$this->change_reason, ucfirst(str_replace('_', ' ', $this->change_reason)));
    }

    public function snapshotValue(string $key, mixed $default = null): mixed
    {
        return $this->snapshot[$key] ?? $default;
    }

    public function snapshotTitle(): ?string
    {
        $title = $this->snapshotValue('resolution_title');

        return is_string($title) && $title !== '' ? $title : null;
    }

    public function snapshotPdfUrl(?Resolution $resolution = null): ?string
    {
        $resolution ??= $this->resolution;
        $path = $this->snapshotValue('pdf_path');

        if (is_string($path) && $path !== '' && $resolution !== null) {
            $absolute = app(PdfAttachmentService::class)->absolutePath($path);

            if ($absolute !== null) {
                return route('resolutions.versions.file', [
                    'resolution' => $resolution,
                    'version' => $this,
                ]);
            }
        }

        $url = $this->snapshotValue('sp_pdf_url');

        return is_string($url) && $url !== '' ? $url : null;
    }
}

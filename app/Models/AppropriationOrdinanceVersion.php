<?php

namespace App\Models;

use App\Services\AppropriationOrdinancePdfService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppropriationOrdinanceVersion extends Model
{
    protected $fillable = [
        'appropriation_ordinance_id',
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

    public function appropriationOrdinance(): BelongsTo
    {
        return $this->belongsTo(AppropriationOrdinance::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function changeReasonLabel(): string
    {
        return config(
            'appropriation_ordinances.version_reasons.'.$this->change_reason,
            ucfirst(str_replace('_', ' ', $this->change_reason)),
        );
    }

    public function snapshotValue(string $key, mixed $default = null): mixed
    {
        return $this->snapshot[$key] ?? $default;
    }

    public function snapshotTitle(): ?string
    {
        $title = $this->snapshotValue('subject');

        return is_string($title) && $title !== '' ? $title : null;
    }

    public function snapshotPdfUrl(?AppropriationOrdinance $record = null): ?string
    {
        $record ??= $this->appropriationOrdinance;
        $path = $this->snapshotValue('pdf_path');

        if (is_string($path) && $path !== '' && $record !== null) {
            $absolute = app(AppropriationOrdinancePdfService::class)->absolutePath($path);

            if ($absolute !== null) {
                return route('appropriation-ordinances.versions.file', [
                    'appropriationOrdinance' => $record,
                    'version' => $this,
                ]);
            }
        }

        $url = $this->snapshotValue('pdf_url');

        return is_string($url) && $url !== '' ? $url : null;
    }
}

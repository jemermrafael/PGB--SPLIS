<?php

namespace App\Models;

use App\Services\OrdinancePdfService;
use App\Support\OrdinancePdfType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdinanceVersion extends Model
{
    protected $fillable = [
        'ordinance_id',
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

    public function ordinance(): BelongsTo
    {
        return $this->belongsTo(Ordinance::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function changeReasonLabel(): string
    {
        return config('ordinances.version_reasons.'.$this->change_reason, ucfirst(str_replace('_', ' ', $this->change_reason)));
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

    public function snapshotPdfUrl(string $type = OrdinancePdfType::MAIN, ?Ordinance $ordinance = null): ?string
    {
        $ordinance ??= $this->ordinance;
        $config = OrdinancePdfType::config($type);
        $path = $this->snapshotValue($config['path']);

        if (is_string($path) && $path !== '' && $ordinance !== null) {
            $absolute = app(OrdinancePdfService::class)->absolutePath($path);

            if ($absolute !== null) {
                return route('ordinances.versions.file', [
                    'ordinance' => $ordinance,
                    'version' => $this,
                    'type' => $type,
                ]);
            }
        }

        $url = $this->snapshotValue($config['url']);

        return is_string($url) && $url !== '' ? $url : null;
    }
}

<?php

namespace App\Models;

use App\Services\AgendaPdfService;
use App\Support\AgendaPdfSlot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgendaItemVersion extends Model
{
    protected $fillable = [
        'agenda_item_id',
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

    public function agendaItem(): BelongsTo
    {
        return $this->belongsTo(AgendaItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function changeReasonLabel(): string
    {
        return config('agenda.version_reasons.'.$this->change_reason, ucfirst(str_replace('_', ' ', $this->change_reason)));
    }

    public function snapshotValue(string $key, mixed $default = null): mixed
    {
        return $this->snapshot[$key] ?? $default;
    }

    public function snapshotRequestPdfUrl(?AgendaItem $agenda = null): ?string
    {
        $agenda ??= $this->agendaItem;
        $path = $this->snapshotValue('request_pdf_path');

        if (is_string($path) && $path !== '' && $agenda !== null) {
            $absolute = app(AgendaPdfService::class)->absolutePath($path);

            if ($absolute !== null) {
                return route('agenda.versions.file', [
                    'agenda' => $agenda,
                    'version' => $this,
                    'slot' => AgendaPdfSlot::REQUEST,
                ]);
            }
        }

        $url = $this->snapshotValue('request_pdf_url');

        return is_string($url) && $url !== '' ? $url : null;
    }

    public function snapshotTitle(): ?string
    {
        $title = $this->snapshotValue('title');

        return is_string($title) && $title !== '' ? $title : null;
    }

    public function snapshotOutputLabel(): ?string
    {
        $number = $this->snapshotValue('reso_ord_ao_no');
        $series = $this->snapshotValue('reso_ord_ao_series');

        if (! is_string($number) || trim($number) === '') {
            return null;
        }

        $number = trim($number);

        if ($series !== null && $series !== '') {
            return $number.' / '.$series;
        }

        return $number;
    }

    public function snapshotOutputTypeLabel(): ?string
    {
        $type = $this->snapshotValue('reso_ord_ao_type');

        if (! is_string($type) || $type === '') {
            return null;
        }

        return config('agenda.measure_types.'.$type, $type);
    }
}

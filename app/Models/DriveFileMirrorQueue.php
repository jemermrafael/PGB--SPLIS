<?php

namespace App\Models;

use App\Support\AgendaPdfSlot;
use App\Support\DriveMirrorEntity;
use App\Support\OrdinancePdfType;
use Illuminate\Database\Eloquent\Model;

class DriveFileMirrorQueue extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $table = 'drive_file_mirror_queue';

    protected $fillable = [
        'entity_type',
        'entity_id',
        'document_slot',
        'source_url',
        'status',
        'result_path',
        'error_message',
        'attempts',
        'queued_at',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function documentLabel(): string
    {
        return match ($this->entity_type) {
            DriveMirrorEntity::ORDINANCE => OrdinancePdfType::config($this->document_slot)['label'],
            DriveMirrorEntity::APPROPRIATION_ORDINANCE => 'PDF',
            DriveMirrorEntity::AGENDA_ITEM => AgendaPdfSlot::config($this->document_slot)['label'],
            default => $this->document_slot,
        };
    }

    public function entityLabel(): string
    {
        $type = DriveMirrorEntity::label($this->entity_type);

        return $type.' #'.$this->entity_id;
    }

    public function summaryLabel(): string
    {
        return $this->entityLabel().' — '.$this->documentLabel();
    }
}

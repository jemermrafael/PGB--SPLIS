<?php

namespace App\Models;

use App\Services\SessionCommitteeReportFileService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegislativeSessionCommitteeReportFile extends Model
{
    protected $fillable = [
        'legislative_session_id',
        'original_filename',
        'stored_path',
        'mime_type',
        'file_size',
        'sort_order',
        'created_by',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(LegislativeSession::class, 'legislative_session_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publicUrl(): ?string
    {
        return app(SessionCommitteeReportFileService::class)->publicUrl($this);
    }

    public function viewerMode(): ?string
    {
        return app(SessionCommitteeReportFileService::class)->viewerMode($this);
    }

    public function existsLocally(): bool
    {
        return app(SessionCommitteeReportFileService::class)->exists($this);
    }
}

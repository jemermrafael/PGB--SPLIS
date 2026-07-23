<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyAttendanceSheet extends Model
{
    protected $fillable = [
        'year',
        'month',
        'content',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'content' => 'array',
        ];
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @return array{
     *   prepared_by: array{name: string, title: string},
     *   noted_by: array{name: string, title: string},
     *   approved_by: array{name: string, title: string},
     *   member_remarks: array<string, string>
     * }
     */
    public function normalizedContent(): array
    {
        $content = is_array($this->content) ? $this->content : [];

        return [
            'prepared_by' => [
                'name' => trim((string) ($content['prepared_by']['name'] ?? '')),
                'title' => trim((string) ($content['prepared_by']['title'] ?? '')),
            ],
            'noted_by' => [
                'name' => trim((string) ($content['noted_by']['name'] ?? '')),
                'title' => trim((string) ($content['noted_by']['title'] ?? '')),
            ],
            'approved_by' => [
                'name' => trim((string) ($content['approved_by']['name'] ?? '')),
                'title' => trim((string) ($content['approved_by']['title'] ?? '')),
            ],
            'member_remarks' => collect($content['member_remarks'] ?? [])
                ->mapWithKeys(fn ($value, $key) => [(string) $key => trim((string) $value)])
                ->filter(fn (string $value) => $value !== '')
                ->all(),
        ];
    }
}

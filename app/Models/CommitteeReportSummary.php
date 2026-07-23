<?php

namespace App\Models;

use App\Support\ObTitleMarkup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommitteeReportSummary extends Model
{
    protected $fillable = [
        'legislative_session_id',
        'title',
        'report_date',
        'content',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'content' => 'array',
        ];
    }

    public function legislativeSession(): BelongsTo
    {
        return $this->belongsTo(LegislativeSession::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return array<string, mixed>
     */
    public function normalizedContent(): array
    {
        $content = is_array($this->content) ? $this->content : [];
        $title = trim((string) ($this->title ?? ''));

        $groups = collect(is_array($content['groups'] ?? null) ? $content['groups'] : [])
            ->map(function ($group) {
                if (! is_array($group)) {
                    return null;
                }

                $group['items'] = collect($group['items'] ?? [])
                    ->filter(fn ($item) => is_array($item))
                    ->map(function (array $item) {
                        $body = trim((string) ($item['body'] ?? ''));
                        $recommendation = trim((string) ($item['recommendation'] ?? ''));

                        return [
                            'agenda_item_id' => $item['agenda_item_id'] ?? null,
                            'agenda_no' => (string) ($item['agenda_no'] ?? ''),
                            'body' => $body,
                            'body_html' => ObTitleMarkup::forTitle(
                                is_string($item['body_html'] ?? null) ? $item['body_html'] : null,
                                $body,
                            ),
                            'recommendation' => $recommendation,
                            'recommendation_html' => ObTitleMarkup::forTitle(
                                is_string($item['recommendation_html'] ?? null) ? $item['recommendation_html'] : null,
                                $recommendation,
                            ),
                        ];
                    })
                    ->values()
                    ->all();

                return $group;
            })
            ->filter()
            ->values()
            ->all();

        return [
            'title_html' => ObTitleMarkup::forTitle(
                is_string($content['title_html'] ?? null) ? $content['title_html'] : null,
                $title,
            ),
            'groups' => $groups,
            'prepared_by' => [
                'name' => trim((string) ($content['prepared_by']['name'] ?? '')),
                'title' => trim((string) ($content['prepared_by']['title'] ?? '')),
            ],
            'reviewed_by' => [
                'name' => trim((string) ($content['reviewed_by']['name'] ?? '')),
                'title' => trim((string) ($content['reviewed_by']['title'] ?? '')),
            ],
        ];
    }
}

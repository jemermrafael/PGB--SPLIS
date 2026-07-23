<?php

namespace App\Services;

use App\Enums\ObBlockType;
use App\Models\AgendaItem;
use App\Models\CommitteeReportSummary;
use App\Models\LegislativeSession;
use App\Models\ObBlock;
use App\Support\CommitteeLookup;
use App\Support\ObAgendaSnapshot;
use App\Support\ObTitleMarkup;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CommitteeReportSummaryService
{
    /**
     * @return array{name: string, title: string}
     */
    public function defaultPreparedBy(): array
    {
        return [
            'name' => 'MARJORIE ANNE G. ORANI',
            'title' => 'Board Secretary II',
        ];
    }

    /**
     * @return array{name: string, title: string}
     */
    public function defaultReviewedBy(): array
    {
        return [
            'name' => 'MARY ANN R. DE JESUS, MPA',
            'title' => "Prov'l Gov't Assistant Department Head",
        ];
    }

    public function ensureForSession(LegislativeSession $session, ?int $userId = null): CommitteeReportSummary
    {
        $summary = CommitteeReportSummary::query()
            ->firstOrCreate(
                ['legislative_session_id' => $session->id],
                [
                    'title' => 'SUMMARY OF COMMITTEE REPORT',
                    'report_date' => $session->session_date?->toDateString() ?? now()->toDateString(),
                    'content' => [
                        'title_html' => null,
                        'groups' => [],
                        'prepared_by' => $this->defaultPreparedBy(),
                        'reviewed_by' => $this->defaultReviewedBy(),
                    ],
                    'created_by' => $userId,
                ],
            );

        $content = $summary->normalizedContent();

        if (($content['groups'] ?? []) === []) {
            $this->syncFromSessionOb($summary, preserveRecommendations: false);
            $summary->refresh();
        }

        return $summary;
    }

    public function syncFromSessionOb(CommitteeReportSummary $summary, bool $preserveRecommendations = true): CommitteeReportSummary
    {
        $session = $summary->legislativeSession()->with('obDocument.blocks')->first();
        $existing = $preserveRecommendations ? $this->itemPreservationMap($summary) : [];
        $groups = $this->buildGroupsFromOb($session, $existing)->all();

        $content = $summary->normalizedContent();
        $content['groups'] = $groups;

        if (blank($content['prepared_by']['name'] ?? null)) {
            $content['prepared_by'] = $this->defaultPreparedBy();
        }
        if (blank($content['reviewed_by']['name'] ?? null)) {
            $content['reviewed_by'] = $this->defaultReviewedBy();
        }

        $summary->forceFill([
            'content' => $content,
            'report_date' => $summary->report_date ?? $session?->session_date,
        ])->save();

        return $summary->fresh();
    }

    /**
     * @param  array{
     *   title?: string|null,
     *   title_html?: string|null,
     *   report_date?: string|null,
     *   prepared_by?: array{name?: string|null, title?: string|null},
     *   reviewed_by?: array{name?: string|null, title?: string|null},
     *   bodies?: array<string, string|null>,
     *   bodies_html?: array<string, string|null>,
     *   recommendations?: array<string, string|null>,
     *   recommendations_html?: array<string, string|null>
     * }  $payload
     */
    public function update(CommitteeReportSummary $summary, array $payload): CommitteeReportSummary
    {
        $content = $summary->normalizedContent();
        $bodies = $payload['bodies'] ?? [];
        $bodiesHtml = $payload['bodies_html'] ?? [];
        $recommendations = $payload['recommendations'] ?? [];
        $recommendationsHtml = $payload['recommendations_html'] ?? [];

        $groups = collect($content['groups'] ?? [])
            ->map(function (array $group) use ($bodies, $bodiesHtml, $recommendations, $recommendationsHtml): array {
                $group['items'] = collect($group['items'] ?? [])
                    ->map(function (array $item) use ($bodies, $bodiesHtml, $recommendations, $recommendationsHtml): array {
                        $key = $this->itemKey($item);

                        if (array_key_exists($key, $bodies)) {
                            $item['body'] = trim((string) $bodies[$key]);
                        }

                        if (array_key_exists($key, $bodiesHtml)) {
                            $item['body_html'] = ObTitleMarkup::forTitle(
                                is_string($bodiesHtml[$key]) ? $bodiesHtml[$key] : null,
                                (string) ($item['body'] ?? ''),
                            );
                        }

                        if (array_key_exists($key, $recommendations)) {
                            $item['recommendation'] = trim((string) $recommendations[$key]);
                        }

                        if (array_key_exists($key, $recommendationsHtml)) {
                            $item['recommendation_html'] = ObTitleMarkup::forTitle(
                                is_string($recommendationsHtml[$key]) ? $recommendationsHtml[$key] : null,
                                (string) ($item['recommendation'] ?? ''),
                            );
                        }

                        return $item;
                    })
                    ->values()
                    ->all();

                return $group;
            })
            ->values()
            ->all();

        $content['groups'] = $groups;
        $content['prepared_by'] = [
            'name' => trim((string) ($payload['prepared_by']['name'] ?? $content['prepared_by']['name'] ?? '')),
            'title' => trim((string) ($payload['prepared_by']['title'] ?? $content['prepared_by']['title'] ?? '')),
        ];
        $content['reviewed_by'] = [
            'name' => trim((string) ($payload['reviewed_by']['name'] ?? $content['reviewed_by']['name'] ?? '')),
            'title' => trim((string) ($payload['reviewed_by']['title'] ?? $content['reviewed_by']['title'] ?? '')),
        ];

        $title = trim((string) ($payload['title'] ?? $summary->title)) ?: 'SUMMARY OF COMMITTEE REPORT';
        $content['title_html'] = array_key_exists('title_html', $payload)
            ? ObTitleMarkup::forTitle(
                is_string($payload['title_html'] ?? null) ? $payload['title_html'] : null,
                $title,
            )
            : ($content['title_html'] ?? null);

        $summary->forceFill([
            'title' => $title,
            'report_date' => $payload['report_date'] ?? $summary->report_date,
            'content' => $content,
        ])->save();

        return $summary->fresh();
    }

    /**
     * @param  array<string, array{
     *   body?: string,
     *   body_html?: string|null,
     *   recommendation?: string,
     *   recommendation_html?: string|null
     * }>  $existingItems
     * @return Collection<int, array<string, mixed>>
     */
    protected function buildGroupsFromOb(?LegislativeSession $session, array $existingItems): Collection
    {
        if ($session?->obDocument === null) {
            return collect();
        }

        $blocks = $session->obDocument->blocks
            ->filter(fn (ObBlock $block) => $block->type === ObBlockType::CommitteeReport)
            ->sortBy('sort_order')
            ->values();

        $agendaIds = $blocks
            ->flatMap(fn (ObBlock $block) => $this->agendaIdsFromBlock($block))
            ->unique()
            ->values();

        $agendas = AgendaItem::query()
            ->whereIn('id', $agendaIds->all())
            ->get()
            ->keyBy('id');

        $grouped = [];

        foreach ($blocks as $block) {
            $content = $block->content ?? [];
            $committeeId = is_numeric($content['committee_id'] ?? null) ? (int) $content['committee_id'] : null;
            $committeeName = (string) ($content['committee_name'] ?? '');
            $displayCommittee = $this->committeeHeading($committeeId, $committeeName);
            $groupKey = $committeeId
                ? 'id:'.$committeeId
                : 'name:'.Str::slug($displayCommittee);

            if (! isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [
                    'key' => $groupKey,
                    'committee_name' => $displayCommittee,
                    'chair_name' => $this->chairLine($committeeId, $committeeName, (string) ($content['chair_name'] ?? '')),
                    'items' => [],
                ];
            }

            foreach ($this->agendaIdsFromBlock($block) as $agendaId) {
                $agenda = $agendas->get($agendaId);
                if (! $agenda instanceof AgendaItem) {
                    continue;
                }

                $agendaNo = ObAgendaSnapshot::agendaNo($agenda);
                $key = $this->itemKey([
                    'agenda_item_id' => $agenda->id,
                    'agenda_no' => $agendaNo,
                ]);
                $existing = $existingItems[$key] ?? [];
                $freshBody = trim((string) ($agenda->title ?: ''));
                $previousBody = trim((string) ($existing['body'] ?? ''));

                if ($previousBody !== '' && $previousBody !== $freshBody) {
                    $body = $previousBody;
                    $bodyHtml = ObTitleMarkup::sanitize(
                        is_string($existing['body_html'] ?? null) ? $existing['body_html'] : null,
                    );
                } else {
                    $body = $freshBody;
                    $bodyHtml = ObTitleMarkup::forTitle(
                        is_string($existing['body_html'] ?? null) ? $existing['body_html'] : null,
                        $body,
                    );
                }

                $recommendation = trim((string) ($existing['recommendation'] ?? ''));
                $recommendationHtml = ObTitleMarkup::forTitle(
                    is_string($existing['recommendation_html'] ?? null) ? $existing['recommendation_html'] : null,
                    $recommendation,
                );

                $item = [
                    'agenda_item_id' => $agenda->id,
                    'agenda_no' => $agendaNo,
                    'body' => $body,
                    'body_html' => $bodyHtml,
                    'recommendation' => $recommendation,
                    'recommendation_html' => $recommendationHtml,
                ];

                $already = collect($grouped[$groupKey]['items'])
                    ->contains(fn (array $row) => (int) ($row['agenda_item_id'] ?? 0) === $agenda->id);

                if (! $already) {
                    $grouped[$groupKey]['items'][] = $item;
                }
            }
        }

        return collect(array_values($grouped))
            ->filter(fn (array $group) => ($group['items'] ?? []) !== [])
            ->values();
    }

    /**
     * @return list<int>
     */
    protected function agendaIdsFromBlock(ObBlock $block): array
    {
        $ids = [];

        if ($block->agenda_item_id !== null) {
            $ids[] = (int) $block->agenda_item_id;
        }

        foreach ($block->content['agenda_item_ids'] ?? [] as $id) {
            if (is_numeric($id)) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique($ids));
    }

    protected function committeeHeading(?int $committeeId, ?string $committeeName): string
    {
        $committee = CommitteeLookup::findById($committeeId) ?? CommitteeLookup::findByName($committeeName);
        $raw = $committee?->name ?: trim((string) $committeeName);

        $label = preg_replace('/^(sp\s+)?committee\s+on\s+/i', '', $raw) ?? $raw;
        $label = trim((string) $label);

        if ($label === '') {
            return 'COMMITTEE';
        }

        if (preg_match('/^committee\s+on\s+/i', $label)) {
            return mb_strtoupper($label);
        }

        return 'COMMITTEE ON '.mb_strtoupper($label);
    }

    protected function chairLine(?int $committeeId, ?string $committeeName, string $fallback): string
    {
        $chair = CommitteeLookup::obChairFor($committeeId, $committeeName);
        if ($chair === '') {
            $chair = trim($fallback);
        }
        if ($chair === '') {
            $chair = CommitteeLookup::chairFor($committeeId, $committeeName);
        }

        return $this->formatChairDisplay($chair);
    }

    public function formatChairDisplay(?string $chair): string
    {
        $chair = preg_replace('/^chair:\s*/i', '', (string) $chair) ?? '';
        $chair = trim($chair);
        $chair = preg_replace('/^board\s+member\s+/iu', 'BM ', $chair) ?? $chair;
        $chair = preg_replace('/\bBoard Member\b/u', 'BM', $chair) ?? $chair;
        $chair = trim($chair);

        return $chair !== '' ? 'Chair: '.$chair : 'Chair:';
    }

    /**
     * @param  array{agenda_item_id?: int|string|null, agenda_no?: string|null}  $item
     */
    public function itemKey(array $item): string
    {
        $id = (int) ($item['agenda_item_id'] ?? 0);
        if ($id > 0) {
            return 'id:'.$id;
        }

        return 'no:'.trim((string) ($item['agenda_no'] ?? ''));
    }

    /**
     * @return array<string, array{
     *   body?: string,
     *   body_html?: string|null,
     *   recommendation?: string,
     *   recommendation_html?: string|null
     * }>
     */
    protected function itemPreservationMap(CommitteeReportSummary $summary): array
    {
        $map = [];

        foreach ($summary->normalizedContent()['groups'] as $group) {
            foreach ($group['items'] ?? [] as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $map[$this->itemKey($item)] = [
                    'body' => trim((string) ($item['body'] ?? '')),
                    'body_html' => is_string($item['body_html'] ?? null) ? $item['body_html'] : null,
                    'recommendation' => trim((string) ($item['recommendation'] ?? '')),
                    'recommendation_html' => is_string($item['recommendation_html'] ?? null) ? $item['recommendation_html'] : null,
                ];
            }
        }

        return $map;
    }
}

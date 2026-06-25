<?php

namespace App\Services;

use App\Models\Resolution;
use App\Models\SptrackImportQueue;
use App\Support\ResolutionNumberParser;
use Illuminate\Support\Collection;

class SptrackMatcher
{
    /** @var array<int, array<int, list<int>>> */
    protected array $sequenceIndex = [];

    /** @var Collection<int, Resolution> */
    protected Collection $resolutions;

    public function __construct()
    {
        $this->resolutions = Resolution::query()
            ->select(['id', 'resolution_no', 'resolution_title', 'series', 'date_approved', 'legacy_file_id', 'sp_sequence'])
            ->get();

        foreach ($this->resolutions as $resolution) {
            $sequence = $resolution->sp_sequence ?? ResolutionNumberParser::extractSequence($resolution->resolution_no);
            if ($sequence === null || ! $resolution->series) {
                continue;
            }

            $this->sequenceIndex[(int) $resolution->series][$sequence][] = (int) $resolution->id;
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{
     *     suggested_resolution_id: ?int,
     *     confidence: string,
     *     match_signals: array<string, mixed>,
     *     proposed_action: string,
     *     sp_sequence: ?int,
     *     notes: ?string
     * }
     */
    public function analyze(array $row): array
    {
        $parsed = ResolutionNumberParser::parseSpResNo($row['sp_res_no'] ?? null);
        $series = $row['sp_series'] ?? null;
        $sequence = $parsed['sequence'];
        $spTitle = $row['sp_title'] ?? null;
        $spDate = $row['sp_date_approved'] ?? null;

        if ($row['legacy_file_id'] && $this->resolutions->firstWhere('legacy_file_id', $row['legacy_file_id'])) {
            $existing = $this->resolutions->firstWhere('legacy_file_id', $row['legacy_file_id']);

            return [
                'suggested_resolution_id' => $existing?->id,
                'confidence' => SptrackImportQueue::CONFIDENCE_HIGH,
                'match_signals' => ['reason' => 'already_linked_by_legacy_file_id'],
                'proposed_action' => SptrackImportQueue::ACTION_ENRICH,
                'sp_sequence' => $sequence,
                'notes' => null,
            ];
        }

        if ($parsed['kind'] === 'ordinance') {
            return [
                'suggested_resolution_id' => null,
                'confidence' => SptrackImportQueue::CONFIDENCE_NONE,
                'match_signals' => ['reason' => 'ordinance_sp_res_no', 'raw' => $parsed['raw']],
                'proposed_action' => SptrackImportQueue::ACTION_REVIEW,
                'sp_sequence' => $sequence,
                'notes' => 'SPResNo appears to be an ordinance number.',
            ];
        }

        if (! $series || $sequence === null || ! $row['sp_res_no']) {
            return [
                'suggested_resolution_id' => null,
                'confidence' => SptrackImportQueue::CONFIDENCE_NONE,
                'match_signals' => ['reason' => 'missing_sp_number_or_series'],
                'proposed_action' => SptrackImportQueue::ACTION_SKIP,
                'sp_sequence' => $sequence,
                'notes' => 'Missing SPResNo or SPSeries.',
            ];
        }

        $candidateIds = $this->sequenceIndex[(int) $series][$sequence] ?? [];
        $exactId = $this->findExactNumberMatch($row['sp_res_no'], (int) $series);

        if ($exactId && ! in_array($exactId, $candidateIds, true)) {
            $candidateIds[] = $exactId;
        }

        if ($candidateIds === []) {
            return [
                'suggested_resolution_id' => null,
                'confidence' => SptrackImportQueue::CONFIDENCE_NONE,
                'match_signals' => [
                    'series' => $series,
                    'sequence' => $sequence,
                    'reason' => 'no_candidate',
                ],
                'proposed_action' => SptrackImportQueue::ACTION_CREATE,
                'sp_sequence' => $sequence,
                'notes' => null,
            ];
        }

        if (count($candidateIds) > 1) {
            $scored = $this->scoreCandidates($candidateIds, $spTitle, $spDate);
            $best = $scored->sortByDesc('score')->first();

            return [
                'suggested_resolution_id' => $best['id'] ?? null,
                'confidence' => SptrackImportQueue::CONFIDENCE_LOW,
                'match_signals' => [
                    'series' => $series,
                    'sequence' => $sequence,
                    'candidates' => $scored->values()->all(),
                    'reason' => 'multiple_candidates',
                ],
                'proposed_action' => SptrackImportQueue::ACTION_REVIEW,
                'sp_sequence' => $sequence,
                'notes' => count($candidateIds).' SPLIS records share this series and sequence.',
            ];
        }

        $candidate = $this->resolutions->firstWhere('id', $candidateIds[0]);
        $signals = $this->buildSignals($candidate, $spTitle, $spDate, $series, $sequence);
        $confidence = $this->determineConfidence($signals);
        $proposedAction = match ($confidence) {
            SptrackImportQueue::CONFIDENCE_HIGH => SptrackImportQueue::ACTION_ENRICH,
            SptrackImportQueue::CONFIDENCE_MEDIUM => SptrackImportQueue::ACTION_REVIEW,
            default => SptrackImportQueue::ACTION_REVIEW,
        };

        if (
            $confidence === SptrackImportQueue::CONFIDENCE_LOW
            && ($signals['title_similarity'] ?? 0) < 30
            && ! ($signals['date_match'] ?? false)
        ) {
            $proposedAction = SptrackImportQueue::ACTION_REVIEW;
        }

        return [
            'suggested_resolution_id' => $candidate?->id,
            'confidence' => $confidence,
            'match_signals' => $signals,
            'proposed_action' => $proposedAction,
            'sp_sequence' => $sequence,
            'notes' => null,
        ];
    }

    protected function findExactNumberMatch(string $spResNo, int $series): ?int
    {
        $match = $this->resolutions->first(function (Resolution $resolution) use ($spResNo, $series) {
            return (int) $resolution->series === $series
                && strcasecmp(trim($resolution->resolution_no), trim($spResNo)) === 0;
        });

        return $match?->id;
    }

    /**
     * @param  list<int>  $candidateIds
     */
    protected function scoreCandidates(array $candidateIds, ?string $spTitle, ?string $spDate): Collection
    {
        return collect($candidateIds)->map(function (int $id) use ($spTitle, $spDate) {
            $candidate = $this->resolutions->firstWhere('id', $id);
            $signals = $this->buildSignals($candidate, $spTitle, $spDate, (int) $candidate?->series, ResolutionNumberParser::extractSequence($candidate?->resolution_no));

            return [
                'id' => $id,
                'resolution_no' => $candidate?->resolution_no,
                'score' => ($signals['title_similarity'] ?? 0) + (($signals['date_match'] ?? false) ? 20 : 0),
                'signals' => $signals,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildSignals(?Resolution $candidate, ?string $spTitle, ?string $spDate, ?int $series, ?int $sequence): array
    {
        $titleSimilarity = ResolutionNumberParser::titleSimilarity($spTitle, $candidate?->resolution_title);
        $dateMatch = $candidate?->date_approved && $spDate
            ? $candidate->date_approved->toDateString() === $spDate
            : false;

        return [
            'series' => $series,
            'sequence' => $sequence,
            'title_similarity' => $titleSimilarity,
            'date_match' => $dateMatch,
            'splis_resolution_no' => $candidate?->resolution_no,
            'splis_title' => $candidate?->resolution_title,
        ];
    }

    /**
     * @param  array<string, mixed>  $signals
     */
    protected function determineConfidence(array $signals): string
    {
        $title = (float) ($signals['title_similarity'] ?? 0);
        $dateMatch = (bool) ($signals['date_match'] ?? false);

        if ($title >= 70 || $dateMatch || $title >= 50) {
            return SptrackImportQueue::CONFIDENCE_HIGH;
        }

        if ($title >= 35) {
            return SptrackImportQueue::CONFIDENCE_MEDIUM;
        }

        return SptrackImportQueue::CONFIDENCE_LOW;
    }
}

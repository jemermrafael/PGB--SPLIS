<?php

namespace App\Services;

use App\Models\Resolution;
use App\Support\ResolutionNumberParser;
use App\Support\TitleSearchNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ResolutionLinkSearch
{
    /**
     * @return Collection<int, Resolution>
     */
    public function search(string $term, ?int $series = null, int $limit = 15): Collection
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return collect();
        }

        if ($series === null && preg_match('/^(\d{4})-/', $term, $matches)) {
            $series = (int) $matches[1];
        }

        $normalized = TitleSearchNormalizer::normalize($term);
        $primaryTokens = TitleSearchNormalizer::primaryTokens($term, 5);

        return Resolution::query()
            ->whereNull('incoming_document_id')
            ->where(function (Builder $query) use ($term, $normalized, $primaryTokens) {
                $this->applyPhraseSearch($query, $term);

                if (mb_strlen($normalized) >= 3) {
                    $query->orWhere('resolution_title', 'like', '%'.$normalized.'%');
                }

                $this->applyResolutionNumberSearch($query, $term);

                if (count($primaryTokens) >= 2) {
                    $query->orWhere(function (Builder $inner) use ($primaryTokens) {
                        foreach ($primaryTokens as $token) {
                            $inner->where('resolution_title', 'like', '%'.$token.'%');
                        }
                    });
                }
            })
            ->when($series, fn (Builder $query) => $query->where('series', $series))
            ->orderByDesc('series')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'resolution_no', 'series', 'resolution_title', 'date_approved']);
    }

    protected function applyPhraseSearch(Builder $query, string $term): void
    {
        $like = '%'.$term.'%';
        $query->where('resolution_no', 'like', $like)
            ->orWhere('resolution_title', 'like', $like);
    }

    protected function applyResolutionNumberSearch(Builder $query, string $term): void
    {
        if (preg_match('/^(\d{4})-(\d+)$/', $term, $matches)) {
            $year = (int) $matches[1];
            $sequence = (int) $matches[2];
            $query->orWhere('resolution_no', ResolutionNumberParser::buildOfficialNumber($year, $sequence))
                ->orWhere('resolution_no', 'like', '%'.$year.'%-%'.sprintf('%04d', $sequence))
                ->orWhere('resolution_no', 'like', '%'.$year.'%-%'.$sequence);
        }

        $sequence = ResolutionNumberParser::extractSequence($term);
        if ($sequence !== null) {
            $query->orWhere('resolution_no', 'like', '%'.sprintf('%04d', $sequence))
                ->orWhere('resolution_no', 'like', '%-'.$sequence);
        }
    }
}

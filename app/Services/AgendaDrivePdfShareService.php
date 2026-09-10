<?php

namespace App\Services;

use App\Models\AgendaItem;
use App\Models\BoardMemberCommitteeReport;
use App\Models\LegislativeSessionCommitteeReportFile;
use App\Support\AgendaPdfSlot;
use Illuminate\Support\Facades\Storage;

class AgendaDrivePdfShareService
{
    public function __construct(
        protected GoogleDrivePdfDownloader $downloader,
        protected AgendaPdfService $pdfs,
    ) {}

    /**
     * Canonical key for matching identical Drive/direct file links.
     */
    public function normalizeUrlKey(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if ($this->downloader->extractFolderId($url) !== null) {
            return null;
        }

        $fileId = $this->downloader->extractFileId($url);

        if ($fileId !== null) {
            return 'drive:'.$fileId;
        }

        return 'url:'.mb_strtolower($url);
    }

    /**
     * Find an existing local path for the same Drive/file URL on another agenda (same slot).
     */
    public function findExistingLocalPath(string $url, string $slot, ?int $exceptAgendaId = null): ?string
    {
        if (! AgendaPdfSlot::isValid($slot)) {
            return null;
        }

        $key = $this->normalizeUrlKey($url);

        if ($key === null) {
            return null;
        }

        $config = AgendaPdfSlot::config($slot);
        $urlColumn = $config['url'];
        $pathColumn = $config['path'];

        $query = AgendaItem::query()
            ->whereNotNull($urlColumn)
            ->where($urlColumn, '!=', '')
            ->whereNotNull($pathColumn)
            ->where($pathColumn, '!=', '')
            ->orderBy('id');

        if ($exceptAgendaId !== null) {
            $query->whereKeyNot($exceptAgendaId);
        }

        foreach ($query->cursor() as $agenda) {
            /** @var AgendaItem $agenda */
            $candidateUrl = trim((string) ($agenda->{$urlColumn} ?? ''));

            if ($this->normalizeUrlKey($candidateUrl) !== $key) {
                continue;
            }

            $path = trim((string) ($agenda->{$pathColumn} ?? ''));

            if ($path !== '' && $this->pdfs->absolutePath($path) !== null) {
                return $path;
            }
        }

        return null;
    }

    public function attachSharedPath(AgendaItem $agenda, string $slot, string $path): void
    {
        $pathColumn = AgendaPdfSlot::config($slot)['path'];
        $agenda->forceFill([$pathColumn => $path])->save();
    }

    /**
     * Groups of agendas sharing the same Drive/file URL for a slot.
     *
     * @return list<array{
     *     slot: string,
     *     key: string,
     *     sample_url: string,
     *     agenda_count: int,
     *     distinct_paths: list<string>,
     *     existing_paths: list<string>,
     *     agenda_ids: list<int>
     * }>
     */
    public function duplicateGroups(?string $slotFilter = null): array
    {
        $slots = $slotFilter !== null && AgendaPdfSlot::isValid($slotFilter)
            ? [$slotFilter]
            : AgendaPdfSlot::all();

        $groups = [];

        foreach ($slots as $slot) {
            $config = AgendaPdfSlot::config($slot);
            $urlColumn = $config['url'];
            $pathColumn = $config['path'];

            $rows = AgendaItem::query()
                ->whereNotNull($urlColumn)
                ->where($urlColumn, '!=', '')
                ->orderBy('id')
                ->get(['id', $urlColumn, $pathColumn]);

            /** @var array<string, array{sample_url: string, agenda_ids: list<int>, paths: list<string>}> $bucket */
            $bucket = [];

            foreach ($rows as $agenda) {
                $url = trim((string) ($agenda->{$urlColumn} ?? ''));
                $key = $this->normalizeUrlKey($url);

                if ($key === null) {
                    continue;
                }

                if (! isset($bucket[$key])) {
                    $bucket[$key] = [
                        'sample_url' => $url,
                        'agenda_ids' => [],
                        'paths' => [],
                    ];
                }

                $bucket[$key]['agenda_ids'][] = (int) $agenda->id;
                $path = trim((string) ($agenda->{$pathColumn} ?? ''));

                if ($path !== '') {
                    $bucket[$key]['paths'][] = $path;
                }
            }

            foreach ($bucket as $key => $data) {
                if (count($data['agenda_ids']) < 2) {
                    continue;
                }

                $distinctPaths = array_values(array_unique($data['paths']));
                $existingPaths = array_values(array_filter(
                    $distinctPaths,
                    fn (string $path) => $this->pdfs->absolutePath($path) !== null,
                ));

                $groups[] = [
                    'slot' => $slot,
                    'key' => $key,
                    'sample_url' => $data['sample_url'],
                    'agenda_count' => count($data['agenda_ids']),
                    'distinct_paths' => $distinctPaths,
                    'existing_paths' => $existingPaths,
                    'agenda_ids' => $data['agenda_ids'],
                ];
            }
        }

        usort($groups, function (array $a, array $b): int {
            return [$b['agenda_count'], $a['slot'], $a['key']] <=> [$a['agenda_count'], $b['slot'], $b['key']];
        });

        return $groups;
    }

    /**
     * Point all agendas that share a Drive URL at one local file, then purge unused duplicates.
     *
     * @return array{
     *     groups: int,
     *     linked: int,
     *     purged: int,
     *     kept_paths: int,
     *     dry_run: bool,
     *     details: list<string>
     * }
     */
    public function dedupe(bool $dryRun = false, ?string $slotFilter = null): array
    {
        $groups = $this->duplicateGroups($slotFilter);
        $linked = 0;
        $purged = 0;
        $keptPaths = 0;
        $details = [];

        foreach ($groups as $group) {
            $slot = $group['slot'];
            $pathColumn = AgendaPdfSlot::config($slot)['path'];
            $keeper = $group['existing_paths'][0] ?? null;

            if ($keeper === null) {
                $details[] = sprintf(
                    '%s · %d agendas · no local file yet · %s',
                    AgendaPdfSlot::config($slot)['label'],
                    $group['agenda_count'],
                    $group['sample_url'],
                );

                continue;
            }

            $keptPaths++;
            $groupLinked = 0;

            $agendas = AgendaItem::query()
                ->whereIn('id', $group['agenda_ids'])
                ->orderBy('id')
                ->get();

            foreach ($agendas as $agenda) {
                $current = trim((string) ($agenda->{$pathColumn} ?? ''));

                if ($current === $keeper) {
                    continue;
                }

                if (! $dryRun) {
                    $this->attachSharedPath($agenda, $slot, $keeper);
                }

                $groupLinked++;
                $linked++;
            }

            $obsolete = array_values(array_filter(
                $group['distinct_paths'],
                fn (string $path) => $path !== $keeper,
            ));

            $groupPurged = 0;

            foreach ($obsolete as $path) {
                if ($this->pathIsReferenced($path, $dryRun ? $group['agenda_ids'] : [])) {
                    continue;
                }

                if (! $dryRun && $this->pdfs->absolutePath($path) !== null) {
                    Storage::disk('local')->delete($path);
                }

                $groupPurged++;
                $purged++;
            }

            $details[] = sprintf(
                '%s · %d agendas → %s (%d relinked, %d purged)',
                AgendaPdfSlot::config($slot)['label'],
                $group['agenda_count'],
                $keeper,
                $groupLinked,
                $groupPurged,
            );
        }

        return [
            'groups' => count($groups),
            'linked' => $linked,
            'purged' => $purged,
            'kept_paths' => $keptPaths,
            'dry_run' => $dryRun,
            'details' => $details,
        ];
    }

    /**
     * @param  list<int>  $ignoreAgendaIds
     */
    protected function pathIsReferenced(string $path, array $ignoreAgendaIds = []): bool
    {
        foreach (AgendaPdfSlot::all() as $slot) {
            $column = AgendaPdfSlot::config($slot)['path'];

            $query = AgendaItem::query()->where($column, $path);

            if ($ignoreAgendaIds !== []) {
                $query->whereNotIn('id', $ignoreAgendaIds);
            }

            if ($query->exists()) {
                return true;
            }
        }

        if (BoardMemberCommitteeReport::query()->where('pdf_path', $path)->exists()) {
            return true;
        }

        return LegislativeSessionCommitteeReportFile::query()
            ->where('stored_path', $path)
            ->exists();
    }
}

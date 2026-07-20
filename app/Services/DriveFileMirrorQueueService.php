<?php

namespace App\Services;

use App\Models\AgendaItem;
use App\Models\AppropriationOrdinance;
use App\Models\DriveFileMirrorQueue;
use App\Models\Ordinance;
use App\Support\AgendaPdfSlot;
use App\Support\DriveMirrorEntity;
use App\Support\OrdinancePdfType;
use Illuminate\Support\Collection;

class DriveFileMirrorQueueService
{
    public function __construct(
        protected OrdinancePdfService $ordinancePdfs,
        protected AppropriationOrdinancePdfService $appropriationPdfs,
        protected AgendaPdfService $agendaPdfs,
        protected OrdinancePdfMirrorService $ordinanceMirror,
        protected AppropriationOrdinancePdfMirrorService $appropriationMirror,
        protected AgendaPdfMirrorService $agendaMirror,
    ) {}

    /**
     * @return array{pending: int, processing: int, completed: int, failed: int}
     */
    public function stats(): array
    {
        $counts = DriveFileMirrorQueue::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'pending' => (int) ($counts[DriveFileMirrorQueue::STATUS_PENDING] ?? 0),
            'processing' => (int) ($counts[DriveFileMirrorQueue::STATUS_PROCESSING] ?? 0),
            'completed' => (int) ($counts[DriveFileMirrorQueue::STATUS_COMPLETED] ?? 0),
            'failed' => (int) ($counts[DriveFileMirrorQueue::STATUS_FAILED] ?? 0),
        ];
    }

    /**
     * @return Collection<int, DriveFileMirrorQueue>
     */
    public function listItems(int $limit = 50): Collection
    {
        return DriveFileMirrorQueue::query()
            ->whereIn('status', [
                DriveFileMirrorQueue::STATUS_PENDING,
                DriveFileMirrorQueue::STATUS_PROCESSING,
            ])
            ->orderByRaw("CASE status WHEN 'processing' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, DriveFileMirrorQueue>
     */
    public function failedItems(int $limit = 50): Collection
    {
        return DriveFileMirrorQueue::query()
            ->where('status', DriveFileMirrorQueue::STATUS_FAILED)
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array{enqueued: int, completed: int, removed: int}
     */
    public function rebuildQueue(): array
    {
        $enqueued = 0;
        $completed = 0;
        $removed = 0;

        foreach (Ordinance::query()->orderBy('id')->cursor() as $ordinance) {
            /** @var Ordinance $ordinance */
            foreach (OrdinancePdfType::all() as $slot) {
                $result = $this->syncQueueRow(
                    DriveMirrorEntity::ORDINANCE,
                    (int) $ordinance->id,
                    $slot,
                    trim((string) ($ordinance->{OrdinancePdfType::config($slot)['url']} ?? '')),
                    $this->ordinancePdfs->existsFor($ordinance, $slot),
                );

                $enqueued += $result['enqueued'];
                $completed += $result['completed'];
                $removed += $result['removed'];
            }
        }

        foreach (AppropriationOrdinance::query()->orderBy('id')->cursor() as $record) {
            /** @var AppropriationOrdinance $record */
            $result = $this->syncQueueRow(
                DriveMirrorEntity::APPROPRIATION_ORDINANCE,
                (int) $record->id,
                'main',
                trim((string) ($record->pdf_url ?? '')),
                $this->appropriationPdfs->existsFor($record),
            );

            $enqueued += $result['enqueued'];
            $completed += $result['completed'];
            $removed += $result['removed'];
        }

        foreach (AgendaItem::query()->orderBy('id')->cursor() as $agenda) {
            /** @var AgendaItem $agenda */
            foreach (AgendaPdfSlot::all() as $slot) {
                $config = AgendaPdfSlot::config($slot);
                $result = $this->syncQueueRow(
                    DriveMirrorEntity::AGENDA_ITEM,
                    (int) $agenda->id,
                    $slot,
                    trim((string) ($agenda->{$config['url']} ?? '')),
                    $this->agendaPdfs->existsFor($agenda, $slot),
                );

                $enqueued += $result['enqueued'];
                $completed += $result['completed'];
                $removed += $result['removed'];
            }
        }

        return compact('enqueued', 'completed', 'removed');
    }

    /**
     * @return array{processed: int, succeeded: int, failed: int}
     */
    public function processBatch(int $limit = 5): array
    {
        $processed = 0;
        $succeeded = 0;
        $failed = 0;

        $items = DriveFileMirrorQueue::query()
            ->where('status', DriveFileMirrorQueue::STATUS_PENDING)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($items as $item) {
            $item->update([
                'status' => DriveFileMirrorQueue::STATUS_PROCESSING,
                'started_at' => now(),
                'attempts' => $item->attempts + 1,
            ]);

            $result = $this->processItem($item);

            if ($result['ok']) {
                $item->update([
                    'status' => DriveFileMirrorQueue::STATUS_COMPLETED,
                    'result_path' => $result['path'] ?? $item->result_path,
                    'error_message' => null,
                    'completed_at' => now(),
                ]);
                $succeeded++;
            } else {
                $item->update([
                    'status' => DriveFileMirrorQueue::STATUS_FAILED,
                    'error_message' => $result['message'],
                    'completed_at' => now(),
                ]);
                $failed++;
            }

            $processed++;
        }

        return compact('processed', 'succeeded', 'failed');
    }

    /**
     * @return array{ok: bool, message: string, path?: string}
     */
    public function processItem(DriveFileMirrorQueue $item): array
    {
        return match ($item->entity_type) {
            DriveMirrorEntity::ORDINANCE => $this->processOrdinance($item),
            DriveMirrorEntity::APPROPRIATION_ORDINANCE => $this->processAppropriation($item),
            DriveMirrorEntity::AGENDA_ITEM => $this->processAgenda($item),
            default => ['ok' => false, 'message' => 'Unknown entity type.'],
        };
    }

    /**
     * @return array{enqueued: int, completed: int, removed: int}
     */
    protected function syncQueueRow(
        string $entityType,
        int $entityId,
        string $slot,
        string $url,
        bool $hasLocal,
    ): array {
        $enqueued = 0;
        $completed = 0;
        $removed = 0;

        $existing = DriveFileMirrorQueue::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('document_slot', $slot)
            ->first();

        if ($url === '' || $hasLocal) {
            if ($existing !== null) {
                if ($hasLocal && $existing->status !== DriveFileMirrorQueue::STATUS_COMPLETED) {
                    $existing->update([
                        'status' => DriveFileMirrorQueue::STATUS_COMPLETED,
                        'completed_at' => now(),
                        'error_message' => null,
                    ]);
                    $completed++;
                } elseif ($url === '') {
                    $existing->delete();
                    $removed++;
                }
            }

            return compact('enqueued', 'completed', 'removed');
        }

        if ($existing === null) {
            DriveFileMirrorQueue::create([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'document_slot' => $slot,
                'source_url' => $url,
                'status' => DriveFileMirrorQueue::STATUS_PENDING,
                'queued_at' => now(),
            ]);
            $enqueued++;

            return compact('enqueued', 'completed', 'removed');
        }

        $updates = ['source_url' => $url];

        if ($existing->source_url !== $url || $existing->status === DriveFileMirrorQueue::STATUS_FAILED) {
            $updates['status'] = DriveFileMirrorQueue::STATUS_PENDING;
            $updates['queued_at'] = now();
            $updates['error_message'] = null;
            $updates['result_path'] = null;
            $updates['completed_at'] = null;
            $enqueued++;
        }

        $existing->update($updates);

        return compact('enqueued', 'completed', 'removed');
    }

    /**
     * @return array{ok: bool, message: string, path?: string}
     */
    protected function processOrdinance(DriveFileMirrorQueue $item): array
    {
        $ordinance = Ordinance::query()->find($item->entity_id);

        if ($ordinance === null) {
            return ['ok' => false, 'message' => 'Ordinance not found.'];
        }

        $result = $this->ordinanceMirror->mirror($ordinance, $item->document_slot);

        return [
            'ok' => $result['ok'],
            'message' => $result['message'],
            'path' => $result['path'] ?? null,
        ];
    }

    /**
     * @return array{ok: bool, message: string, path?: string}
     */
    protected function processAppropriation(DriveFileMirrorQueue $item): array
    {
        $record = AppropriationOrdinance::query()->find($item->entity_id);

        if ($record === null) {
            return ['ok' => false, 'message' => 'Appropriation Ordinance not found.'];
        }

        $result = $this->appropriationMirror->mirror($record);

        return [
            'ok' => $result['ok'],
            'message' => $result['message'],
            'path' => $result['path'] ?? null,
        ];
    }

    /**
     * @return array{ok: bool, message: string, path?: string}
     */
    protected function processAgenda(DriveFileMirrorQueue $item): array
    {
        $agenda = AgendaItem::query()->find($item->entity_id);

        if ($agenda === null) {
            return ['ok' => false, 'message' => 'Agenda item not found.'];
        }

        $result = $this->agendaMirror->mirror($agenda, $item->document_slot);

        return [
            'ok' => $result['ok'],
            'message' => $result['message'],
            'path' => $result['path'] ?? null,
        ];
    }
}

<?php

namespace App\Services;

use App\Models\Municipality;
use App\Models\Resolution;
use App\Models\SptrackImportQueue;
use App\Models\User;
use App\Support\DocumentType;
use App\Support\ResolutionNumberParser;
use Illuminate\Support\Str;

class SptrackApplier
{
    /** @var array<string, int> */
    protected array $municipalityLookup = [];

    public function apply(?User $user = null, ?string $batchId = null): array
    {
        $this->municipalityLookup = Municipality::query()
            ->get(['id', 'description'])
            ->mapWithKeys(fn (Municipality $m) => [strtoupper(trim($m->description)) => $m->id])
            ->all();

        $query = SptrackImportQueue::query()
            ->where('queue_status', SptrackImportQueue::STATUS_APPROVED);

        if ($batchId) {
            $query->where('batch_id', $batchId);
        }

        $stats = ['enriched' => 0, 'created' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($query->orderBy('legacy_file_id')->cursor() as $item) {
            try {
                $result = $this->applyItem($item, $user);
                $stats[$result]++;
            } catch (\Throwable) {
                $stats['failed']++;
            }
        }

        return $stats;
    }

    protected function applyItem(SptrackImportQueue $item, ?User $user): string
    {
        $action = $item->effectiveAction();

        if ($action === SptrackImportQueue::ACTION_SKIP) {
            $item->update([
                'queue_status' => SptrackImportQueue::STATUS_SKIPPED,
                'applied_by' => $user?->id,
                'applied_at' => now(),
            ]);

            return 'skipped';
        }

        if ($action === SptrackImportQueue::ACTION_CREATE) {
            $resolution = $this->createResolution($item);
            $item->update([
                'queue_status' => SptrackImportQueue::STATUS_APPLIED,
                'user_resolution_id' => $resolution->id,
                'applied_by' => $user?->id,
                'applied_at' => now(),
            ]);

            return 'created';
        }

        $resolutionId = $item->targetResolutionId();
        if (! $resolutionId) {
            throw new \RuntimeException('Enrich action requires a target resolution.');
        }

        $resolution = Resolution::query()->findOrFail($resolutionId);
        $this->enrichResolution($resolution, $item);
        $item->update([
            'queue_status' => SptrackImportQueue::STATUS_APPLIED,
            'applied_by' => $user?->id,
            'applied_at' => now(),
        ]);

        return 'enriched';
    }

    protected function enrichResolution(Resolution $resolution, SptrackImportQueue $item): void
    {
        $payload = $this->workflowPayload($item);

        if ($item->sp_title) {
            $payload['resolution_title'] = $item->sp_title;
        }

        $payload['keyword'] = $item->keyword;
        $payload['committee'] = $item->referral;

        if ($item->sp_date_approved) {
            $payload['date_approved'] = $item->sp_date_approved;
        }

        if ($item->sptrack_status) {
            $payload['status'] = $this->mapStatus($item->sptrack_status);
        }

        $municipalityId = $this->resolveMunicipalityId($item->municipality);
        if ($municipalityId) {
            $payload['municipality_id'] = $municipalityId;
        }

        $resolution->update($payload);
    }

    protected function createResolution(SptrackImportQueue $item): Resolution
    {
        $sequence = $item->sp_sequence ?? ResolutionNumberParser::parseSpResNo($item->sp_res_no)['sequence'];
        $series = (int) $item->sp_series;
        $resolutionNo = $sequence
            ? ResolutionNumberParser::buildOfficialNumber($series, $sequence)
            : (string) $item->sp_res_no;

        return Resolution::create(array_merge($this->workflowPayload($item), [
            'resolution_no' => Str::limit($resolutionNo, 50, ''),
            'resolution_title' => $item->sp_title ?: ($item->mun_title ?: 'Untitled'),
            'document_type' => DocumentType::RESOLUTION,
            'series' => $series,
            'keyword' => $item->keyword,
            'committee' => $item->referral,
            'date_approved' => $item->sp_date_approved,
            'municipality_id' => $this->resolveMunicipalityId($item->municipality),
            'status' => $this->mapStatus($item->sptrack_status ?? 'Approved'),
            'created_by' => null,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    protected function workflowPayload(SptrackImportQueue $item): array
    {
        return [
            'legacy_file_id' => $item->legacy_file_id,
            'legacy_sp_res_no' => $item->sp_res_no,
            'sp_sequence' => $item->sp_sequence,
            'mun_resolution_no' => $item->mun_resolution_no,
            'mun_title' => $item->mun_title,
            'mun_series' => $item->mun_series,
            'date_received' => $item->date_received,
            'action_taken' => $item->action_taken,
            'agenda' => $item->agenda,
            'concerned_agency' => $item->concerned_agency,
            'remarks' => $item->remarks,
            'sp_pdf_url' => $item->sp_pdf_url,
            'mun_pdf_url' => $item->mun_pdf_url,
        ];
    }

    protected function resolveMunicipalityId(?string $name): ?int
    {
        if (! $name) {
            return null;
        }

        $key = strtoupper(trim($name));
        if (isset($this->municipalityLookup[$key])) {
            return $this->municipalityLookup[$key];
        }

        foreach ($this->municipalityLookup as $label => $id) {
            if (str_contains($key, $label) || str_contains($label, $key)) {
                return $id;
            }
        }

        return null;
    }

    protected function mapStatus(?string $status): string
    {
        $status = strtolower(trim((string) $status));

        return match (true) {
            str_contains($status, 'approved') => 'approved',
            str_contains($status, 'agenda') => 'pending',
            default => 'draft',
        };
    }
}

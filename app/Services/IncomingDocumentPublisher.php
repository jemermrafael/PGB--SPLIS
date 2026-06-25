<?php

namespace App\Services;

use App\Models\IncomingDocument;
use App\Models\Municipality;
use App\Models\Resolution;
use App\Support\ResolutionNumberParser;
use Illuminate\Support\Str;

class IncomingDocumentPublisher
{
    /** @var array<string, int> */
    protected array $municipalityLookup = [];

    public function prefill(IncomingDocument $incoming): Resolution
    {
        $this->municipalityLookup = Municipality::query()
            ->get(['id', 'description'])
            ->mapWithKeys(fn (Municipality $m) => [strtoupper(trim($m->description)) => $m->id])
            ->all();

        $series = (int) ($incoming->sp_series ?? 0);
        $parsed = ResolutionNumberParser::parseSpResNo($incoming->sp_res_no);
        $sequence = $parsed['sequence'];

        $resolutionNo = '';
        if ($series > 0 && $sequence) {
            $resolutionNo = ResolutionNumberParser::buildOfficialNumber($series, $sequence);
        } elseif ($incoming->sp_res_no) {
            $resolutionNo = trim((string) $incoming->sp_res_no);
        }

        $attributes = array_merge($this->workflowAttributes($incoming), [
            'resolution_no' => $resolutionNo,
            'resolution_title' => $incoming->sp_title ?: ($incoming->title ?: ''),
            'series' => $series > 0 ? $series : (int) date('Y'),
            'date_approved' => $incoming->sp_date_approved,
            'keyword' => $incoming->keyword,
            'committee' => $incoming->referral,
            'municipality_id' => $this->resolveMunicipalityId($incoming->municipality),
            'status' => $incoming->sp_date_approved ? 'approved' : 'draft',
        ]);

        return new Resolution($attributes);
    }

    /**
     * @return array<string, mixed>
     */
    public function workflowAttributes(IncomingDocument $incoming): array
    {
        $sequence = ResolutionNumberParser::parseSpResNo($incoming->sp_res_no)['sequence'];

        return [
            'legacy_file_id' => $incoming->legacy_file_id,
            'legacy_sp_res_no' => $incoming->sp_res_no,
            'sp_sequence' => $sequence,
            'mun_resolution_no' => $incoming->mun_resolution_no,
            'mun_title' => $incoming->title,
            'mun_series' => $incoming->mun_series,
            'date_received' => $incoming->date_received,
            'action_taken' => $incoming->action_taken,
            'agenda' => $incoming->agenda,
            'concerned_agency' => $incoming->concerned_agency,
            'remarks' => $incoming->remarks,
            'sp_pdf_url' => $incoming->sp_pdf_url,
            'mun_pdf_url' => $incoming->mun_pdf_url,
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

    public function suggestResolutionNo(IncomingDocument $incoming): string
    {
        return Str::limit($this->prefill($incoming)->resolution_no, 50, '');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Ordinance;
use App\Services\OrdinancePdfMirrorService;
use Illuminate\Http\RedirectResponse;

class OrdinancePdfMirrorController extends Controller
{
    public function __invoke(Ordinance $ordinance, OrdinancePdfMirrorService $mirror): RedirectResponse
    {
        $this->authorize('update', $ordinance);

        $result = $mirror->mirror($ordinance, overwrite: false);

        if ($result['ok'] && ! str_contains($result['message'], 'skipped')) {
            ActivityLog::record('ordinance.pdf_mirrored', $ordinance, [
                'pdf_path' => $result['path'] ?? null,
            ]);
        }

        return redirect()
            ->route('ordinances.show', $ordinance)
            ->with($result['ok'] ? 'status' : 'error', $result['message']);
    }
}

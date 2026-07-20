<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AppropriationOrdinance;
use App\Services\AppropriationOrdinancePdfMirrorService;
use Illuminate\Http\RedirectResponse;

class AppropriationOrdinancePdfMirrorController extends Controller
{
    public function __invoke(AppropriationOrdinance $appropriationOrdinance, AppropriationOrdinancePdfMirrorService $mirror): RedirectResponse
    {
        $this->authorize('update', $appropriationOrdinance);

        $result = $mirror->mirror($appropriationOrdinance, overwrite: false);

        if ($result['ok'] && ! str_contains($result['message'], 'skipped')) {
            ActivityLog::record('appropriation_ordinance.pdf_mirrored', $appropriationOrdinance, [
                'pdf_path' => $result['path'] ?? null,
            ]);
        }

        return redirect()
            ->route('appropriation-ordinances.show', $appropriationOrdinance)
            ->with($result['ok'] ? 'status' : 'error', $result['message']);
    }
}

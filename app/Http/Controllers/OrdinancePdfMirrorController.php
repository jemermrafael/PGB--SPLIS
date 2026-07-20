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

        $result = $mirror->mirrorAllFor($ordinance, overwrite: false);

        if ($result['mirrored'] > 0) {
            ActivityLog::record('ordinance.pdf_mirrored', $ordinance, [
                'mirrored' => $result['mirrored'],
                'messages' => $result['messages'],
            ]);
        }

        if ($result['mirrored'] > 0 && $result['failed'] === 0) {
            $message = $result['mirrored'] === 1
                ? ($result['messages'][0] ?? 'PDF mirrored.')
                : $result['mirrored'].' PDF(s) mirrored from Drive.';
        } elseif ($result['mirrored'] > 0) {
            $message = $result['mirrored'].' PDF(s) mirrored. '.$result['failed'].' failed.';
        } elseif ($result['failed'] > 0) {
            $message = implode(' ', $result['messages']);
        } else {
            $message = 'All linked PDFs are already stored locally.';
        }

        return redirect()
            ->route('ordinances.show', $ordinance)
            ->with($result['failed'] > 0 && $result['mirrored'] === 0 ? 'error' : 'status', $message);
    }
}

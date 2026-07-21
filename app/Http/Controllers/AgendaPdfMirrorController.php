<?php

namespace App\Http\Controllers;

use App\Models\AgendaItem;
use App\Services\AgendaPdfMirrorService;
use Illuminate\Http\RedirectResponse;

class AgendaPdfMirrorController extends Controller
{
    public function __invoke(AgendaItem $agenda, AgendaPdfMirrorService $mirror): RedirectResponse
    {
        $this->authorize('update', $agenda);

        $result = $mirror->mirrorAllFor($agenda, overwrite: false);

        if ($result['mirrored'] > 0 && $result['failed'] === 0) {
            $message = $result['mirrored'] === 1
                ? ($result['messages'][0] ?? 'File mirrored.')
                : $result['mirrored'].' file(s) mirrored from Drive.';
        } elseif ($result['mirrored'] > 0) {
            $message = $result['mirrored'].' file(s) mirrored. '.$result['failed'].' failed.';
        } elseif ($result['failed'] > 0) {
            $message = implode(' ', $result['messages']);
        } else {
            $message = 'All linked documents are already stored locally.';
        }

        return redirect()
            ->route('agenda.show', $agenda)
            ->with($result['failed'] > 0 && $result['mirrored'] === 0 ? 'error' : 'status', $message);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\AgendaItem;
use App\Services\AgendaItemRequestFileService;
use Illuminate\Http\RedirectResponse;

class AgendaRequestFileImportController extends Controller
{
    public function __invoke(
        AgendaItem $agenda,
        AgendaItemRequestFileService $files,
    ): RedirectResponse {
        $this->authorize('update', $agenda);

        $result = $files->importFromDisk($agenda, auth()->id());

        if ($result['registered'] === 0) {
            return redirect()
                ->route('agenda.show', $agenda)
                ->with('status', 'No new local request packet files found under agenda/'.$agenda->id.'.');
        }

        return redirect()
            ->route('agenda.show', $agenda)
            ->with('status', $result['registered'].' local request packet file(s) registered (folders preserved).');
    }
}

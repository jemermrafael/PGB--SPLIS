<?php

namespace App\Http\Controllers;

use App\Models\AgendaItem;
use App\Models\AgendaItemRequestFile;
use App\Services\AgendaItemRequestFileService;
use Illuminate\Http\RedirectResponse;

class AgendaRequestFileDeleteController extends Controller
{
    public function __invoke(
        AgendaItem $agenda,
        AgendaItemRequestFile $file,
        AgendaItemRequestFileService $files,
    ): RedirectResponse {
        $this->authorize('update', $agenda);

        abort_unless((int) $file->agenda_item_id === (int) $agenda->id, 404);

        $files->delete($file);

        return redirect()
            ->route('agenda.show', $agenda)
            ->with('status', 'Request packet file removed.');
    }
}

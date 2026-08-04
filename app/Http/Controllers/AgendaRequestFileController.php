<?php

namespace App\Http\Controllers;

use App\Models\AgendaItem;
use App\Models\AgendaItemRequestFile;
use App\Services\AgendaItemRequestFileService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgendaRequestFileController extends Controller
{
    public function __invoke(
        AgendaItem $agenda,
        AgendaItemRequestFile $file,
        AgendaItemRequestFileService $files,
    ): StreamedResponse {
        $this->authorize('view', $agenda);

        abort_unless((int) $file->agenda_item_id === (int) $agenda->id, 404);

        return $files->stream($file);
    }
}

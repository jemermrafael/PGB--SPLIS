<?php

namespace App\Http\Controllers;

use App\Models\AgendaItem;
use App\Models\AgendaItemVersion;
use App\Services\AgendaPdfService;
use App\Support\AgendaPdfSlot;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgendaVersionPdfController extends Controller
{
    public function __invoke(
        AgendaItem $agenda,
        AgendaItemVersion $version,
        string $slot,
        AgendaPdfService $pdfs,
    ): StreamedResponse {
        abort_unless($version->agenda_item_id === $agenda->id, 404);
        $this->authorize('view', $agenda);
        abort_unless(AgendaPdfSlot::isValid($slot), 404);

        $pathColumn = AgendaPdfSlot::config($slot)['path'];
        $relative = $version->snapshotValue($pathColumn);

        abort_unless(is_string($relative) && $relative !== '', 404, 'No local file for this version.');

        return $pdfs->streamRelative($relative);
    }
}

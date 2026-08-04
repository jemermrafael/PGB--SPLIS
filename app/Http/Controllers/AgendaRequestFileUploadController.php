<?php

namespace App\Http\Controllers;

use App\Models\AgendaItem;
use App\Services\AgendaItemRequestFileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AgendaRequestFileUploadController extends Controller
{
    public function __invoke(
        Request $request,
        AgendaItem $agenda,
        AgendaItemRequestFileService $files,
    ): RedirectResponse {
        $this->authorize('update', $agenda);

        $validated = $request->validate([
            'relative_folder' => ['nullable', 'string', 'max:500'],
            'request_packet_files' => ['required', 'array', 'min:1'],
            'request_packet_files.*' => ['file', 'mimes:pdf,jpg,jpeg,png,gif,webp,doc,docx', 'max:51200'],
        ]);

        $folder = $validated['relative_folder'] ?? null;
        $count = 0;

        foreach ($request->file('request_packet_files', []) as $uploaded) {
            if ($uploaded === null) {
                continue;
            }
            $files->store($uploaded, $agenda, $folder, $request->user()?->id);
            $count++;
        }

        return redirect()
            ->route('agenda.show', $agenda)
            ->with('status', $count === 1
                ? '1 request packet file uploaded.'
                : $count.' request packet files uploaded.');
    }
}

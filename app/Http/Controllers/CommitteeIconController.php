<?php

namespace App\Http\Controllers;

use App\Models\Committee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommitteeIconController extends Controller
{
    public function __invoke(Request $request, Committee $committee): StreamedResponse
    {
        abort_unless($request->user() !== null, 403);
        $this->authorize('view', $committee);

        abort_unless(
            filled($committee->icon_path) && Storage::disk('local')->exists($committee->icon_path),
            404,
        );

        $mime = Storage::disk('local')->mimeType($committee->icon_path) ?: 'image/png';

        return Storage::disk('local')->response(
            $committee->icon_path,
            basename($committee->icon_path),
            [
                'Content-Type' => $mime,
                'Cache-Control' => 'private, max-age=3600',
            ],
        );
    }
}

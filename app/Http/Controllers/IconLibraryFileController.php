<?php

namespace App\Http\Controllers;

use App\Models\IconLibraryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IconLibraryFileController extends Controller
{
    public function __invoke(Request $request, IconLibraryItem $iconLibraryItem): StreamedResponse
    {
        abort_unless($request->user() !== null, 403);
        abort_unless($iconLibraryItem->existsLocally(), 404);

        $mime = $iconLibraryItem->mime_type
            ?: (Storage::disk('local')->mimeType($iconLibraryItem->stored_path) ?: 'image/png');

        return Storage::disk('local')->response(
            $iconLibraryItem->stored_path,
            $iconLibraryItem->original_filename,
            [
                'Content-Type' => $mime,
                'Cache-Control' => 'private, max-age=3600',
            ],
        );
    }
}

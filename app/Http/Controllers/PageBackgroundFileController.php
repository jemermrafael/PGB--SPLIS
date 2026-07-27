<?php

namespace App\Http\Controllers;

use App\Models\PageBackground;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PageBackgroundFileController extends Controller
{
    public function __invoke(Request $request, PageBackground $pageBackground): StreamedResponse
    {
        abort_unless($request->user() !== null, 403);
        abort_unless($pageBackground->hasImage(), 404);

        $mime = $pageBackground->mime_type
            ?: (Storage::disk('local')->mimeType($pageBackground->image_path) ?: 'image/jpeg');

        return Storage::disk('local')->response(
            $pageBackground->image_path,
            $pageBackground->image_original_filename ?: 'background',
            [
                'Content-Type' => $mime,
                'Cache-Control' => 'private, max-age=3600',
            ],
        );
    }
}

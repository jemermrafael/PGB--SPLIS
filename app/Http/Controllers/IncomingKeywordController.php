<?php

namespace App\Http\Controllers;

use App\Models\IncomingDocument;
use App\Support\IncomingFieldOptions;
use Illuminate\Http\JsonResponse;

class IncomingKeywordController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $this->authorize('viewAny', IncomingDocument::class);

        return response()->json([
            'data' => IncomingFieldOptions::keywords(),
        ]);
    }
}

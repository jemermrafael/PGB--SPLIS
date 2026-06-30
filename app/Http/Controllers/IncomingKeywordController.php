<?php

namespace App\Http\Controllers;

use App\Support\IncomingFieldOptions;
use Illuminate\Http\JsonResponse;

class IncomingKeywordController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => IncomingFieldOptions::keywords(),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Support\AgendaDeadline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgendaDeadlinePreviewController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(
            AgendaDeadline::preview($request->only(['date_received', 'prescribed_days', 'status']))
        );
    }
}

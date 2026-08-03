<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\MyResolutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MyResolutionController extends Controller
{
    public function index(Request $request, MyResolutionService $service): View|RedirectResponse
    {
        $user = $this->boardMemberUser($request);

        if ($user === null) {
            return redirect()->route('resolutions.index');
        }

        if ($user->board_member_id === null) {
            return view('board-member.resolutions.index', [
                'user' => $user,
                'seriesYears' => collect(),
                'unlinked' => true,
            ]);
        }

        return view('board-member.resolutions.index', [
            'user' => $user,
            'seriesYears' => $service->seriesYearsForUser($user),
            'unlinked' => false,
        ]);
    }

    public function search(Request $request, MyResolutionService $service): JsonResponse|RedirectResponse
    {
        $user = $this->boardMemberUser($request);

        if ($user === null) {
            return redirect()->route('resolutions.index');
        }

        if ($user->board_member_id === null) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 15,
                    'total' => 0,
                ],
            ]);
        }

        $paginator = $service->paginateForUser($request, $user);

        return response()->json([
            'data' => collect($paginator->items())->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    protected function boardMemberUser(Request $request): ?User
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isBoardMember()) {
            return null;
        }

        return $user;
    }
}

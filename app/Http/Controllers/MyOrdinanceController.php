<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\MyOrdinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MyOrdinanceController extends Controller
{
    public function index(Request $request, MyOrdinanceService $service): View|RedirectResponse
    {
        $user = $this->boardMemberUser($request);

        if ($user === null) {
            return redirect()->route('ordinances.index');
        }

        if ($user->board_member_id === null) {
            return view('board-member.ordinances.my', [
                'user' => $user,
                'records' => collect(),
                'seriesYears' => collect(),
                'unlinked' => true,
            ]);
        }

        return view('board-member.ordinances.my', [
            'user' => $user,
            'records' => $service->paginateForMember($request, (int) $user->board_member_id),
            'seriesYears' => $service->seriesYearsForMember((int) $user->board_member_id),
            'unlinked' => false,
        ]);
    }

    public function all(Request $request, MyOrdinanceService $service): View|RedirectResponse
    {
        if ($this->boardMemberUser($request) === null) {
            return redirect()->route('ordinances.index');
        }

        return view('board-member.ordinances.all', [
            'records' => $service->paginateAll($request),
            'seriesYears' => $service->seriesYears(),
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

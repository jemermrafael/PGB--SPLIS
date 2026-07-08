<?php

namespace App\Http\Controllers;

use App\Models\BoardMember;
use App\Models\Ordinance;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BoardMemberOrdinanceReportController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->canRecordAttendance(), 403);

        $boardMembers = BoardMember::query()
            ->where('is_active', true)
            ->ordered()
            ->get();

        $selectedMember = null;
        $ordinances = collect();

        if ($request->filled('board_member_id')) {
            $selectedMember = BoardMember::query()->find($request->integer('board_member_id'));

            if ($selectedMember) {
                $ordinances = $selectedMember->ordinances()
                    ->ordered()
                    ->paginate(20)
                    ->withQueryString();
            }
        }

        return view('board-members.ordinances-report', [
            'boardMembers' => $boardMembers,
            'selectedMember' => $selectedMember,
            'ordinances' => $ordinances,
        ]);
    }
}

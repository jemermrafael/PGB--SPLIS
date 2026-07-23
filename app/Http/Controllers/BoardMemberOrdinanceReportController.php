<?php

namespace App\Http\Controllers;

use App\Models\BoardMember;
use App\Models\Ordinance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BoardMemberOrdinanceReportController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->canRecordAttendance(), 403);

        $boardMemberId = $request->filled('board_member_id')
            ? $request->integer('board_member_id')
            : null;

        return view('board-members.ordinances-report', [
            'boardMembers' => BoardMember::query()
                ->where('is_active', true)
                ->ordered()
                ->get(),
            'selectedMemberId' => $boardMemberId,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        abort_unless($request->user()?->canRecordAttendance(), 403);

        $memberId = $request->integer('board_member_id');

        if ($memberId <= 0) {
            return response()->json([
                'data' => [],
                'meta' => $this->emptyMeta(),
                'member' => null,
            ]);
        }

        $selectedMember = BoardMember::query()->find($memberId);

        if ($selectedMember === null) {
            return response()->json([
                'data' => [],
                'meta' => $this->emptyMeta(),
                'member' => null,
            ]);
        }

        $role = $request->string('role')->toString();
        $allowedRoles = [
            \App\Enums\OrdinanceBoardMemberRole::Author->value,
            \App\Enums\OrdinanceBoardMemberRole::Sponsor->value,
            \App\Enums\OrdinanceBoardMemberRole::AuthoredSponsored->value,
        ];

        $paginator = Ordinance::query()
            ->whereHas('boardMembers', function ($query) use ($selectedMember, $role, $allowedRoles): void {
                $query->where('board_members.id', $selectedMember->id);

                if (in_array($role, $allowedRoles, true)) {
                    $query->where('ordinance_board_member.role', $role);
                }
            })
            ->ordered()
            ->when($request->filled('q'), fn ($query) => $query->search($request->string('q')->toString()))
            ->paginate(20);

        return response()->json([
            'data' => collect($paginator->items())
                ->map(fn (Ordinance $ordinance) => $this->ordinancePayload($ordinance))
                ->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'member' => [
                'id' => $selectedMember->id,
                'name' => $selectedMember->displayName(),
            ],
        ]);
    }

    /**
     * @return array{current_page: int, last_page: int, per_page: int, total: int}
     */
    private function emptyMeta(): array
    {
        return [
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => 20,
            'total' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ordinancePayload(Ordinance $ordinance): array
    {
        $passed = $ordinance->date_enacted !== null;
        $pdfUrl = $ordinance->pdfPublicUrl();

        return [
            'url' => route('ordinances.show', $ordinance),
            'number_label' => $ordinance->displayNumber(),
            'series_label' => $ordinance->displaySeries(),
            'subject' => \Illuminate\Support\Str::limit($ordinance->listTitle(), 100, '…'),
            'date_enacted' => $ordinance->date_enacted?->toDateString(),
            'date_approved' => $ordinance->date_approved?->toDateString(),
            'status' => $passed ? 'passed' : 'not_passed',
            'status_label' => $passed ? 'Passed' : 'Not passed',
            'has_pdf' => filled($pdfUrl),
            'pdf_url' => $pdfUrl,
        ];
    }
}

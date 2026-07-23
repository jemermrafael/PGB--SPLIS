<?php

namespace App\Services;

use App\Models\BoardMember;
use App\Models\BoardMemberTerm;
use App\Models\CommitteeTerm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BoardMemberRosterService
{
    /**
     * @return Collection<string, Collection<int, BoardMemberTerm>>
     */
    public function rosterGroupedByDistrict(CommitteeTerm $term): Collection
    {
        $districtOrder = config('board_members.districts', []);

        $assignments = BoardMemberTerm::query()
            ->where('committee_term_id', $term->id)
            ->whereIn('district', $districtOrder)
            ->with('boardMember')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return collect($districtOrder)
            ->mapWithKeys(fn (string $district) => [
                $district => $assignments->where('district', $district)->values(),
            ])
            ->filter(fn (Collection $rows) => $rows->isNotEmpty());
    }

    /**
     * Active board members for a term, in the same order as /board-members
     * (district config order → sort_order → assignment id).
     *
     * @return Collection<int, BoardMember>
     */
    public function orderedActiveMembers(CommitteeTerm $term): Collection
    {
        return $this->rosterGroupedByDistrict($term)
            ->flatten(1)
            ->filter(fn (BoardMemberTerm $assignment) => $assignment->is_active && $assignment->boardMember !== null)
            ->map(fn (BoardMemberTerm $assignment) => $assignment->boardMember)
            ->values();
    }

    /**
     * @param  array{district?: string|null, ex_officio_title?: string|null, is_active?: bool}  $data
     */
    public function saveAssignment(BoardMember $boardMember, CommitteeTerm $term, array $data): BoardMemberTerm
    {
        $district = $data['district'] ?? null;
        $exOfficioTitle = $district === 'Ex Officio'
            ? (trim((string) ($data['ex_officio_title'] ?? '')) ?: null)
            : null;

        $assignment = BoardMemberTerm::query()->updateOrCreate(
            [
                'board_member_id' => $boardMember->id,
                'committee_term_id' => $term->id,
            ],
            [
                'district' => $district,
                'ex_officio_title' => $exOfficioTitle,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ],
        );

        if ($term->is_current) {
            $this->syncLegacyFields($boardMember, $assignment);
        }

        return $assignment;
    }

    public function assignmentFor(BoardMember $boardMember, CommitteeTerm $term): ?BoardMemberTerm
    {
        return BoardMemberTerm::query()
            ->where('board_member_id', $boardMember->id)
            ->where('committee_term_id', $term->id)
            ->first();
    }

    /**
     * Election terms where this person appears on the Board Member roster.
     *
     * @return \Illuminate\Support\Collection<int, CommitteeTerm>
     */
    public function termsFor(BoardMember $boardMember): Collection
    {
        return CommitteeTerm::query()
            ->whereHas('boardMemberAssignments', fn (Builder $query) => $query->where('board_member_id', $boardMember->id))
            ->ordered()
            ->get();
    }

    public function termForSeriesYear(int $seriesYear): CommitteeTerm
    {
        $term = CommitteeTerm::query()
            ->where('year_from', '<=', $seriesYear)
            ->where(function (Builder $query) use ($seriesYear): void {
                $query->whereNull('year_to')
                    ->orWhere('year_to', '>=', $seriesYear);
            })
            ->orderByDesc('is_current')
            ->orderByDesc('year_from')
            ->first();

        return $term ?? CommitteeTerm::currentOrCreate();
    }

    /**
     * @return Builder<BoardMember>
     */
    public function activeMembersForTermQuery(CommitteeTerm $term): Builder
    {
        return BoardMember::query()
            ->whereHas('termAssignments', function (Builder $query) use ($term): void {
                $query
                    ->where('committee_term_id', $term->id)
                    ->where('is_active', true);
            })
            ->with(['termAssignments' => fn ($query) => $query->where('committee_term_id', $term->id)])
            ->ordered();
    }

    protected function syncLegacyFields(BoardMember $boardMember, BoardMemberTerm $assignment): void
    {
        $boardMember->forceFill([
            'district' => $assignment->district,
            'is_active' => $assignment->is_active,
        ])->saveQuietly();
    }
}

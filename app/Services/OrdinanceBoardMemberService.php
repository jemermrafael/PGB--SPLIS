<?php

namespace App\Services;

use App\Enums\OrdinanceBoardMemberRole;
use App\Models\Ordinance;
use Illuminate\Support\Facades\DB;

class OrdinanceBoardMemberService
{
    /**
     * @param  list<int>  $authorIds
     * @param  list<int>  $sponsorIds
     * @param  list<int>  $authoredSponsoredIds
     */
    public function sync(
        Ordinance $ordinance,
        array $authorIds,
        array $sponsorIds,
        array $authoredSponsoredIds,
    ): void {
        DB::transaction(function () use ($ordinance, $authorIds, $sponsorIds, $authoredSponsoredIds): void {
            $ordinance->boardMembers()->detach();

            $this->attachMembers($ordinance, OrdinanceBoardMemberRole::Author, $authorIds);
            $this->attachMembers($ordinance, OrdinanceBoardMemberRole::Sponsor, $sponsorIds);
            $this->attachMembers($ordinance, OrdinanceBoardMemberRole::AuthoredSponsored, $authoredSponsoredIds);
        });
    }

    /**
     * @param  list<int>  $memberIds
     */
    protected function attachMembers(Ordinance $ordinance, OrdinanceBoardMemberRole $role, array $memberIds): void
    {
        foreach (array_values(array_unique(array_filter($memberIds))) as $index => $memberId) {
            $ordinance->boardMembers()->attach($memberId, [
                'role' => $role->value,
                'sort_order' => $index,
            ]);
        }
    }
}

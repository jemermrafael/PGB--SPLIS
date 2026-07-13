<?php

namespace Tests\Unit;

use App\Support\CommitteeLookup;
use Tests\TestCase;

class CommitteeLookupTest extends TestCase
{
    public function test_short_agenda_referral_matches_full_committee_name(): void
    {
        $committee = 'Justice, Human Rights, and Legal Matters';

        $this->assertTrue(CommitteeLookup::referralMatchesCommittee('Justice', $committee));
        $this->assertTrue(CommitteeLookup::referralMatchesCommittee('justice', $committee));
    }

    public function test_ampersand_and_and_variants_match(): void
    {
        $committee = 'Peace and Order and Public Safety';

        $this->assertTrue(CommitteeLookup::referralMatchesCommittee(
            'Peace and Order & Public Safety',
            $committee,
        ));
    }

    public function test_finance_short_referral_matches(): void
    {
        $committee = 'Finance, Budget, Appropriation, and Ways & Means';

        $this->assertTrue(CommitteeLookup::referralMatchesCommittee('Finance', $committee));
    }
}

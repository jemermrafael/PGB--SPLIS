<?php

namespace App\Console\Commands;

use App\Services\CommitteeReferralScheduleService;
use Illuminate\Console\Command;

class DispatchScheduledCommitteeReferrals extends Command
{
    protected $signature = 'splis:dispatch-scheduled-committee-referrals';

    protected $description = 'Send due scheduled committee referrals to committee chairmen';

    public function handle(CommitteeReferralScheduleService $service): int
    {
        $count = $service->dispatchDue();

        $this->info("Dispatched {$count} scheduled committee referral(s).");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\LegislativeSession;
use App\Services\AgendaLifecycleService;
use Illuminate\Console\Command;

class SyncObSessionAgendas extends Command
{
    protected $signature = 'ob:sync-lifecycle
                            {session? : Legislative session ID}
                            {--latest : Sync the nearest upcoming draft/scheduled session}';

    protected $description = 'Sync eligible agenda items into Order of Business session(s) using lifecycle rules';

    public function handle(AgendaLifecycleService $lifecycle): int
    {
        $sessionId = $this->argument('session');

        if ($this->option('latest')) {
            $session = $lifecycle->nearestUpcomingSession();

            if ($session === null) {
                $this->error('No upcoming draft/scheduled session with an OB document was found.');

                return self::FAILURE;
            }

            $lifecycle->syncNewSession($session);
            $this->info('Synced agendas for latest upcoming session #'.$session->id.' ('.$session->displayTitle().').');

            return self::SUCCESS;
        }

        if ($sessionId === null) {
            $sessions = LegislativeSession::query()
                ->with('obDocument')
                ->whereIn('status', ['draft', 'scheduled'])
                ->whereDate('session_date', '>=', now()->toDateString())
                ->whereHas('obDocument')
                ->orderBy('session_date')
                ->orderBy('id')
                ->get();

            if ($sessions->isEmpty()) {
                $this->error('No upcoming sessions found to sync.');

                return self::FAILURE;
            }

            foreach ($sessions as $session) {
                $lifecycle->syncNewSession($session);
                $this->line('Synced session #'.$session->id.' ('.$session->displayTitle().')');
            }

            return self::SUCCESS;
        }

        $session = LegislativeSession::query()->with('obDocument')->find($sessionId);

        if ($session === null || ! $session->obDocument) {
            $this->error('Session not found or has no Order of Business document.');

            return self::FAILURE;
        }

        $lifecycle->syncNewSession($session);
        $this->info('Synced agendas for session #'.$session->id.' ('.$session->displayTitle().').');

        return self::SUCCESS;
    }
}

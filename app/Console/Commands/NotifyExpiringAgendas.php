<?php

namespace App\Console\Commands;

use App\Services\AgendaExpirationNotifier;
use Illuminate\Console\Command;

class NotifyExpiringAgendas extends Command
{
    protected $signature = 'splis:notify-expiring-agendas';

    protected $description = 'Notify board members about committee agenda items due within two weeks';

    public function handle(AgendaExpirationNotifier $notifier): int
    {
        $count = $notifier->syncAll();

        $this->info("Processed {$count} expiring agenda item(s).");

        return self::SUCCESS;
    }
}

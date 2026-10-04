<?php

namespace App\Console\Commands;

use App\Modules\Pets\Services\GameEventProcessor;
use App\Modules\Pets\Services\GameEventSchedule;
use Illuminate\Console\Command;

class ProcessGameEvents extends Command
{
    protected $signature = 'events:process';

    protected $description = 'Generate upcoming dog events and freeze or settle due entries.';

    public function handle(GameEventSchedule $schedule, GameEventProcessor $processor): int
    {
        $schedule->ensureUpcoming();
        $this->components->info('Processed '.$processor->processDue().' events.');

        return self::SUCCESS;
    }
}

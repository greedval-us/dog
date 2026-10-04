<?php

namespace App\Console\Commands;

use App\Modules\Pets\Services\GameEventProcessor;
use App\Modules\Pets\Services\GameEventSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class ProcessGameEvents extends Command
{
    protected $signature = 'events:process';

    protected $description = 'Generate upcoming dog events and freeze or settle due entries.';

    public function handle(GameEventSchedule $schedule, GameEventProcessor $processor): int
    {
        $schedule->ensureUpcoming();
        $at = CarbonImmutable::now()->startOfSecond();
        $processed = 0;
        do {
            $processed += $processor->processDue($at);
        } while ($processor->hasDueEvents($at));
        $this->components->info('Processed '.$processed.' events.');

        return self::SUCCESS;
    }
}

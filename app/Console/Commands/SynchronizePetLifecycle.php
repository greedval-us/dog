<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Pets\Exceptions\PendingGameEventRegistration;
use App\Modules\Pets\Services\GameEventProcessor;
use App\Modules\Pets\Services\PetLifecycle;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class SynchronizePetLifecycle extends Command
{
    protected $signature = 'pets:sync-lifecycle';

    protected $description = 'Freeze deceased dogs and automatically retire dogs at six months';

    public function handle(PetLifecycle $lifecycle, GameEventProcessor $events): int
    {
        $at = CarbonImmutable::now()->startOfSecond();
        $count = 0;
        do {
            $events->processDue($at);
        } while ($events->hasDueEvents($at));
        foreach (User::query()->whereHas('pets', fn (Builder $query) => $query->whereNull('retired_at')->whereNull('died_at'))
            ->select('id')->lazyById(200) as $user) {
            if ($events->hasDueRegistrations($user, $at)) {
                continue;
            }
            try {
                $lifecycle->synchronizeOwner($user, $at);
            } catch (PendingGameEventRegistration) {
                continue;
            }
            $count++;
        }
        $this->info('Synchronized pet lifetimes for '.$count.' players.');

        return self::SUCCESS;
    }
}

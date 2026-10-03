<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Pets\Services\PetLifecycle;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class SynchronizePetLifecycle extends Command
{
    protected $signature = 'pets:sync-lifecycle';

    protected $description = 'Freeze deceased dogs and automatically retire dogs at six months';

    public function handle(PetLifecycle $lifecycle): int
    {
        $at = now()->startOfSecond();
        $count = 0;
        foreach (User::query()->whereHas('pets', fn (Builder $query) => $query->whereNull('retired_at')->whereNull('died_at'))
            ->select('id')->lazyById(200) as $user) {
            $lifecycle->synchronizeOwner($user, $at);
            $count++;
        }
        $this->info('Synchronized pet lifetimes for '.$count.' players.');

        return self::SUCCESS;
    }
}

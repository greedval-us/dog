<?php

namespace App\Console\Commands;

use App\Models\Pet;
use App\Models\PetHistoryEntry;
use App\Modules\Pets\Actions\RecordPetThought;
use App\Modules\Pets\Exceptions\PendingGameEventRegistration;
use App\Modules\Players\Enums\PlayerStatus;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MaintainPetHistory extends Command
{
    protected $signature = 'pets:maintain-history {--prune-only : Only remove history older than 30 days}';

    protected $description = 'Prune old pet history and record current thoughts in bounded batches';

    public function handle(RecordPetThought $thought): int
    {
        $removed = 0;
        $cutoff = now()->subDays(30);
        do {
            $ids = PetHistoryEntry::query()->where('occurred_at', '<', $cutoff)->orderBy('id')->limit(1000)->pluck('id');
            $deleted = $ids->isEmpty() ? 0 : PetHistoryEntry::query()->whereIn('id', $ids)->delete();
            $removed += $deleted;
        } while ($deleted > 0);

        $recorded = 0;
        if (! $this->option('prune-only')) {
            Pet::query()->active()->whereNotNull('user_id')
                ->whereHas('user', fn ($query) => $query->where('status', PlayerStatus::Active))
                ->with('user:id')->select(['id', 'user_id'])->chunkById(100, function ($pets) use ($thought, &$recorded): void {
                    foreach ($pets as $pet) {
                        try {
                            if ($pet->user !== null && $thought->handle($pet->user, $pet->id) !== null) {
                                $recorded++;
                            }
                        } catch (ModelNotFoundException|PendingGameEventRegistration) {
                            continue;
                        }
                    }
                });
        }
        $this->info('Removed '.$removed.' old entries; recorded '.$recorded.' thoughts.');

        return self::SUCCESS;
    }
}

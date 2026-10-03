<?php

namespace App\Modules\Pets\Services;

use App\Models\Pet;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\DTO\PetStatusData;
use App\Modules\Pets\Queries\GetPetStatuses;
use Carbon\CarbonImmutable;

final class PetStateSynchronizer
{
    public function __construct(
        private PetDecayCalculator $decay,
        private GetPetStatuses $statuses,
    ) {}

    /** Advance the in-memory snapshot before recalculating its current effects. */
    public function advance(Pet $pet, CarbonImmutable $at): PetStatusData
    {
        $pet->advanceTo($at, $this->decay);

        return $this->synchronize($pet, $at);
    }

    /** Refresh effects after a state change; the calling Action owns persistence. */
    public function synchronize(Pet $pet, CarbonImmutable $at): PetStatusData
    {
        $status = $this->statuses->handle($pet, $at);
        $pet->buffs = $status->buffs;
        $pet->debuffs = $status->debuffs;

        return $status;
    }
}

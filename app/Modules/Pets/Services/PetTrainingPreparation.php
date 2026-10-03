<?php

namespace App\Modules\Pets\Services;

use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Calculators\TrainingRules;
use App\Modules\Pets\DTO\CareOption;
use App\Modules\Pets\DTO\PetStatusData;
use App\Modules\Pets\DTO\PreparedPetCare;
use App\Modules\Pets\Enums\CareRefusal;
use App\Modules\Pets\Enums\PetStat;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Queries\GetExecutableTraining;
use InvalidArgumentException;

final class PetTrainingPreparation
{
    public function __construct(
        private GetExecutableTraining $training,
        private TrainingRules $rules,
        private PetCarePreparation $care,
    ) {}

    public function option(Pet $pet, string $variant): CareOption
    {
        return $this->training->handle($variant) ?? throw new InvalidArgumentException('Unknown care action.');
    }

    /**
     * Uses the care preparation lock/transaction contract and adds only training gains.
     *
     * @param  array<string, int>  $itemIds
     * @param  array<string, int>  $rolls
     */
    public function prepare(User $owner, Pet $pet, string $variant, array $itemIds, CareOption $option, PetStatusData $status, array &$rolls): PreparedPetCare
    {
        $prepared = $this->care->prepare($owner, $pet, $variant, $itemIds, $option, $status, $rolls);
        $base = $option->statGains ?? throw new InvalidArgumentException('Unknown training.');
        $remaining = [];
        foreach ($base as $name => $gain) {
            $stat = PetStat::from($name);
            $remaining[$name] = $pet->getAttribute($stat->potentialColumn()) - $pet->getAttribute($name);
        }
        $gains = $this->rules->gains($base, $prepared->qualities['sports'], $pet->statePercentages(null), $remaining);
        if (array_sum($gains) === 0) {
            throw PetUnavailable::forCare(CareRefusal::TrainingPotential);
        }

        return $prepared->withStatGains($gains);
    }
}

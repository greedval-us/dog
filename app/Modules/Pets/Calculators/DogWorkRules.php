<?php

namespace App\Modules\Pets\Calculators;

final class DogWorkRules
{
    public function valid(int $skillLevel, int $coins, int $gems, int $duration, int $energy, int $limit): bool
    {
        return $skillLevel >= 1 && $skillLevel <= SkillRules::MAX_LEVEL
            && $coins > 0 && $coins <= 1000000000 && $gems >= 0 && $gems <= 1000000000
            && $duration > 0 && $duration <= 1000000000 && $energy >= 0 && $energy <= 1000000000
            && $limit > 0 && $limit <= 1000000000;
    }
}

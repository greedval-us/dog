<?php

namespace App\Modules\Pets\Calculators;

final class DogWorkRules
{
    private const int MAX_COINS_REWARD = 1_000_000_000;

    private const int MAX_GEMS_REWARD = 1_000_000_000;

    private const int MAX_ENERGY_COST = 1_000_000_000;

    private const int MAX_DAILY_PLACES = 1_000_000_000;

    public function valid(int $skillLevel, int $coins, int $gems, int $duration, int $energy, int $limit): bool
    {
        return $skillLevel >= 1 && $skillLevel <= SkillRules::MAX_LEVEL
            && $coins > 0 && $coins <= self::MAX_COINS_REWARD && $gems >= 0 && $gems <= self::MAX_GEMS_REWARD
            && $duration >= GameplayTiming::MIN_SECONDS && $duration <= GameplayTiming::MAX_SECONDS && $energy >= 0 && $energy <= self::MAX_ENERGY_COST
            && $limit > 0 && $limit <= self::MAX_DAILY_PLACES;
    }
}

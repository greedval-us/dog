<?php

namespace App\Modules\Pets\Calculators;

use App\Modules\Pets\Enums\PetStat;

final class TrainingRules
{
    /** @param array<string, mixed> $gains
     * @param  array<string, mixed>  $costs
     */
    public function valid(array $gains, array $costs, int $energy, int $duration, int $cooldown): bool
    {
        if (count($gains) < 1 || count($gains) > 2 || $energy < 1 || $duration < 1 || $cooldown < 1 || $costs === []) {
            return false;
        }
        foreach ($gains as $stat => $gain) {
            if (PetStat::tryFrom($stat) === null || ! is_int($gain) || $gain < 1 || $gain > 100) {
                return false;
            }
        }
        foreach ($costs as $state => $cost) {
            if (! in_array($state, ['health', 'satiety', 'hydration', 'mood', 'cleanliness', 'bond'], true) || ! is_int($cost) || $cost < 1 || $cost > 100) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, int> $base
     * @param  array<string, float>  $states
     * @param  array<string, int>  $remaining
     * @return array<string, int>
     */
    public function gains(array $base, int $quality, array $states, array $remaining): array
    {
        $factor = (0.5 + max(1, min(10, $quality)) / 10)
            * (0.5 + max(0, min(100, $states['mood'])) / 200)
            * (0.5 + max(0, min(100, $states['bond'])) / 200);
        $gains = [];
        foreach ($base as $stat => $gain) {
            $gains[$stat] = min(max(0, $remaining[$stat]), max(1, (int) round($gain * $factor)));
        }

        return $gains;
    }
}

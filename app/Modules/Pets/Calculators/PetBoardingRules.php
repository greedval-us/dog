<?php

namespace App\Modules\Pets\Calculators;

use App\Modules\Pets\Enums\PetBoardingPlan;
use InvalidArgumentException;

/**
 * @phpstan-type BoardingBalance array{inactivity_seconds: int, cheap_hourly_price: int, premium_price_multiplier: int, cheap_stat_decay_multiplier: int, premium_stat_decay_per_hour: float, state_decay_per_hour: array<string, array<string, int|float>>}
 * @phpstan-type BoardingPeriod array{starts_at: int, ends_at: int, plan: string, state_rates: array<string, int|float>, stat_rates: array<string, int|float>, hourly_price?: int}
 */
final class PetBoardingRules
{
    public const SECONDS_PER_HOUR = 3600;

    /** @param BoardingBalance $balance
     * @param  array<string, int|float>  $normalStatDecay
     */
    public function __construct(private array $balance, private array $normalStatDecay) {}

    public function inactivitySeconds(): int
    {
        return max(0, $this->balance['inactivity_seconds']);
    }

    public function hourlyPrice(PetBoardingPlan $plan): int
    {
        return match ($plan) {
            PetBoardingPlan::Off => 0,
            PetBoardingPlan::Cheap => max(1, $this->balance['cheap_hourly_price']),
            PetBoardingPlan::Premium => max(1, $this->balance['cheap_hourly_price']) * max(1, $this->balance['premium_price_multiplier']),
        };
    }

    /** @return BoardingPeriod */
    public function period(PetBoardingPlan $plan, int $startsAt, int $endsAt): array
    {
        $rates = [];
        foreach ($this->normalStatDecay as $stat => $rate) {
            $rates[$stat] = $plan === PetBoardingPlan::Premium
                ? max(0, $this->balance['premium_stat_decay_per_hour'])
                : max(0, $rate) * max(0, $this->balance['cheap_stat_decay_multiplier']);
        }

        return [
            'starts_at' => $startsAt, 'ends_at' => $endsAt, 'plan' => $plan->value,
            'state_rates' => $this->balance['state_decay_per_hour'][$plan->value], 'stat_rates' => $rates,
        ];
    }

    /**
     * Buy hours in timestamp order, then pet ID order, without iterating over offline hours.
     *
     * @param  array<int, array{starts_at: int, through: int}>  $candidates
     * @return array<int, int> Purchased hours per pet.
     */
    public function allocate(array $candidates, int $coins, int $price): array
    {
        if ($coins < 0 || $price < 1) {
            throw new InvalidArgumentException('Invalid boarding budget.');
        }
        if ($candidates === []) {
            return [];
        }
        ksort($candidates);
        $earliest = min(array_column($candidates, 'starts_at')) - 1;
        $latest = max(array_column($candidates, 'through'));
        $budgetHours = intdiv($coins, $price);
        while ($earliest < $latest) {
            $middle = $earliest + intdiv($latest - $earliest + 1, 2);
            $hours = 0;
            foreach ($candidates as $candidate) {
                $hours += $this->hoursThrough($candidate, $middle);
            }
            if ($hours <= $budgetHours) {
                $earliest = $middle;
            } else {
                $latest = $middle - 1;
            }
        }
        $allocated = [];
        $next = [];
        foreach ($candidates as $petId => $candidate) {
            $allocated[$petId] = $this->hoursThrough($candidate, $earliest);
            $budgetHours -= $allocated[$petId];
            $nextAt = $candidate['starts_at'] + $allocated[$petId] * self::SECONDS_PER_HOUR;
            if ($nextAt <= $candidate['through']) {
                $next[$petId] = $nextAt;
            }
        }
        asort($next, SORT_NUMERIC);
        foreach ($next as $petId => $nextAt) {
            if ($budgetHours < 1) {
                break;
            }
            $allocated[$petId]++;
            $budgetHours--;
        }

        return $allocated;
    }

    /** @param array{starts_at: int, through: int} $candidate */
    private function hoursThrough(array $candidate, int $at): int
    {
        $through = min($at, $candidate['through']);

        return $through < $candidate['starts_at'] ? 0 : intdiv($through - $candidate['starts_at'], self::SECONDS_PER_HOUR) + 1;
    }
}

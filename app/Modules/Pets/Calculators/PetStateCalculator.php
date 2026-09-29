<?php

namespace App\Modules\Pets\Calculators;

/**
 * @phpstan-type StateBalance array{decay_per_hour: array<string, int|float>, health_needs: list<string>, health_threshold: int|float, health_loss_per_hour: int|float, energy_needs: list<string>, energy_threshold: int|float, energy_recovery_per_hour: int|float}
 */
final class PetStateCalculator
{
    /** @param StateBalance $balance */
    public function __construct(private array $balance) {}

    /**
     * @param  array<string, float>  $values
     * @param  array<string, int>  $maximums
     * @param  array<string, int>  $modifiers
     * @return array<string, float>
     */
    public function calculate(array $values, array $maximums, int $seconds, array $modifiers = []): array
    {
        if ($seconds <= 0) {
            return $values;
        }

        $hours = $seconds / 3600;
        $rates = $this->balance['decay_per_hour'];
        foreach ($rates as $state => $rate) {
            $rates[$state] = max(0, $rate) * (100 + max(-50, min(50, $modifiers[$state.'_decay_percent'] ?? 0))) / 100;
        }
        $criticalAfter = INF;
        foreach ($this->balance['health_needs'] as $state) {
            $criticalAfter = min($criticalAfter, $this->hoursUntilThreshold(
                $values, $maximums, $state, $this->balance['health_threshold'], $rates[$state] ?? 0, below: true,
            ));
        }
        $healthLoss = max(0, $hours - $criticalAfter) * $this->balance['health_loss_per_hour'];
        $recoveryHours = $hours;

        foreach ($this->balance['energy_needs'] as $state) {
            $untilThreshold = $this->hoursUntilThreshold(
                $values, $maximums, $state, $this->balance['energy_threshold'],
                $state === 'health' ? $this->balance['health_loss_per_hour'] : ($rates[$state] ?? 0),
            );

            if ($state === 'health' && $values[$state] > $maximums[$state] * $this->balance['energy_threshold'] / 100) {
                $untilThreshold += $criticalAfter;
            }

            $recoveryHours = min($recoveryHours, $untilThreshold);
        }

        foreach ($rates as $state => $rate) {
            $values[$state] -= $maximums[$state] * $rate * $hours / 100;
        }

        $values['health'] -= $maximums['health'] * $healthLoss / 100;
        $values['energy'] += $maximums['energy'] * $this->balance['energy_recovery_per_hour'] * $recoveryHours / 100;

        foreach ($values as $state => $value) {
            $values[$state] = round(max(0, min($maximums[$state], $value)), 4);
        }

        return $values;
    }

    /**
     * @param  array<string, float>  $values
     * @param  array<string, int>  $maximums
     */
    private function hoursUntilThreshold(array $values, array $maximums, string $state, float $threshold, float $rate, bool $below = false): float
    {
        $maximum = $maximums[$state];
        $percentage = $maximum > 0 ? max(0, min(100, $values[$state] / $maximum * 100)) : 0;

        if ($percentage <= $threshold) {
            if ($below && $percentage === $threshold && $rate <= 0) {
                return INF;
            }

            return 0.0;
        }

        return $rate > 0 ? ($percentage - $threshold) / $rate : INF;
    }
}

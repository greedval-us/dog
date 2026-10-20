<?php

namespace App\Modules\Pets\Calculators;

/**
 * @phpstan-import-type Effect from PetStatusRules
 * @phpstan-import-type BoardingPeriod from PetBoardingRules
 *
 * @phpstan-type StatBalance array{decay_per_hour: array<string, int|float>, minimum: int}
 */
final class PetDecayCalculator
{
    /** @param StatBalance $balance */
    public function __construct(private PetStateCalculator $states, private PetStatusRules $statuses, private array $balance) {}

    /**
     * @param  array<string, float>  $values
     * @param  array<string, int>  $maximums
     * @param  list<Effect>  $effects
     * @param  list<BoardingPeriod>  $boarding
     * @return array<string, float>
     */
    public function states(array $values, array $maximums, int $from, int $to, array $effects, array $boarding = []): array
    {
        foreach ($this->intervals($from, $to, $effects, $boarding) as [$start, $end, $modifiers, $period]) {
            if ($period !== null) {
                $values['satiety'] = (float) $maximums['satiety'];
                $values['hydration'] = (float) $maximums['hydration'];
            }
            $values = $this->states->calculate($values, $maximums, $end - $start, $modifiers, $period['state_rates'] ?? []);
        }
        if ($to >= $from && $this->boardingAt($boarding, $to) !== null) {
            $values['satiety'] = (float) $maximums['satiety'];
            $values['hydration'] = (float) $maximums['hydration'];
        }

        return $values;
    }

    /**
     * @param  array<string, float>  $values
     * @param  array<string, int>  $maximums
     * @param  list<Effect>  $effects
     * @param  list<BoardingPeriod>  $boarding
     * @return array{values: array<string, float>, diedAt: int|null}
     */
    public function statesUntilDeath(array $values, array $maximums, int $from, int $to, array $effects, array $boarding = []): array
    {
        if ($values['health'] <= 0) {
            return ['values' => $values, 'diedAt' => $from];
        }

        $advanced = $this->states($values, $maximums, $from, $to, $effects, $boarding);
        if ($advanced['health'] > 0) {
            return ['values' => $advanced, 'diedAt' => null];
        }

        $earliest = $from;
        $latest = $to;
        while ($earliest < $latest) {
            $middle = $earliest + intdiv($latest - $earliest, 2);
            if ($this->states($values, $maximums, $from, $middle, $effects, $boarding)['health'] <= 0) {
                $latest = $middle;
            } else {
                $earliest = $middle + 1;
            }
        }

        return ['values' => $this->states($values, $maximums, $from, $earliest, $effects, $boarding), 'diedAt' => $earliest];
    }

    /**
     * @param  array<string, int>  $values
     * @param  array<string, float>  $remainders
     * @param  list<Effect>  $effects
     * @param  list<BoardingPeriod>  $boarding
     * @return array{values: array<string, int>, remainders: array<string, float>}
     */
    public function stats(array $values, array $remainders, int $from, int $to, array $effects, array $boarding = []): array
    {
        foreach ($this->intervals($from, $to, $effects, $boarding) as [$start, $end, $modifiers, $period]) {
            foreach ($values as $stat => $value) {
                $modifier = max(-50, min(50, ($modifiers['stats_decay_percent'] ?? 0) + ($modifiers[$stat.'_decay_percent'] ?? 0)));
                $rate = $period['stat_rates'][$stat] ?? $this->balance['decay_per_hour'][$stat] ?? 0;
                $loss = ($remainders[$stat] ?? 0) + max(0, $rate) * ($end - $start) / PetBoardingRules::SECONDS_PER_HOUR * (100 + $modifier) / 100;
                $whole = (int) floor(round($loss, 10));
                $minimum = min($value, max(0, $this->balance['minimum']));
                $values[$stat] = max($minimum, $value - $whole);
                $remainders[$stat] = $values[$stat] === $minimum ? 0.0 : round($loss - $whole, 10);
            }
        }

        return ['values' => $values, 'remainders' => $remainders];
    }

    /**
     * Split at effect boundaries so expired effects still influence their elapsed time offline.
     *
     * @param  list<Effect>  $effects
     * @param  list<BoardingPeriod>  $boarding
     * @return list<array{int, int, array<string, int>, BoardingPeriod|null}>
     */
    private function intervals(int $from, int $to, array $effects, array $boarding): array
    {
        if ($to <= $from) {
            return [];
        }

        $timed = [];
        $boundaries = [$from, $to];
        foreach ($boarding as $period) {
            if ($period['ends_at'] > $from && $period['starts_at'] < $to) {
                $boundaries[] = max($from, $period['starts_at']);
                $boundaries[] = min($to, $period['ends_at']);
            }
        }
        foreach ($effects as $effect) {
            $end = $effect['expires_at'] ?? (isset($effect['disease_id']) ? $to : 0);
            $start = $effect['starts_at'] ?? ($end - (int) ($effect['duration_seconds'] ?? 0));
            if ($end <= $from || $start >= $to || $end <= $start) {
                continue;
            }
            $timed[] = [...$effect, 'starts_at' => $start, 'expires_at' => $end];
            $boundaries[] = max($from, $start);
            $boundaries[] = min($to, $end);
        }
        $boundaries = array_values(array_unique($boundaries));
        sort($boundaries);
        $intervals = [];
        for ($i = 0; $i < count($boundaries) - 1; $i++) {
            $start = $boundaries[$i];
            $active = array_values(array_filter($timed, fn (array $effect): bool => $effect['starts_at'] <= $start && $effect['expires_at'] > $start));
            $intervals[] = [$start, $boundaries[$i + 1], $this->statuses->modifiers($active), $this->boardingAt($boarding, $start)];
        }

        return $intervals;
    }

    /** @param list<BoardingPeriod> $boarding
     * @return BoardingPeriod|null
     */
    private function boardingAt(array $boarding, int $at): ?array
    {
        foreach ($boarding as $period) {
            if ($period['starts_at'] <= $at && $period['ends_at'] > $at) {
                return $period;
            }
        }

        return null;
    }
}

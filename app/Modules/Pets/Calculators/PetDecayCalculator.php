<?php

namespace App\Modules\Pets\Calculators;

/**
 * @phpstan-import-type Effect from PetStatusRules
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
     * @return array<string, float>
     */
    public function states(array $values, array $maximums, int $from, int $to, array $effects): array
    {
        foreach ($this->intervals($from, $to, $effects) as [$start, $end, $modifiers]) {
            $values = $this->states->calculate($values, $maximums, $end - $start, $modifiers);
        }

        return $values;
    }

    /**
     * @param  array<string, int>  $values
     * @param  array<string, float>  $remainders
     * @param  list<Effect>  $effects
     * @return array{values: array<string, int>, remainders: array<string, float>}
     */
    public function stats(array $values, array $remainders, int $from, int $to, array $effects): array
    {
        foreach ($this->intervals($from, $to, $effects) as [$start, $end, $modifiers]) {
            foreach ($values as $stat => $value) {
                $modifier = max(-50, min(50, ($modifiers['stats_decay_percent'] ?? 0) + ($modifiers[$stat.'_decay_percent'] ?? 0)));
                $loss = ($remainders[$stat] ?? 0) + max(0, $this->balance['decay_per_hour'][$stat] ?? 0) * ($end - $start) / 3600 * (100 + $modifier) / 100;
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
     * @return list<array{int, int, array<string, int>}>
     */
    private function intervals(int $from, int $to, array $effects): array
    {
        if ($to <= $from) {
            return [];
        }

        $timed = [];
        $boundaries = [$from, $to];
        foreach ($effects as $effect) {
            $end = $effect['expires_at'] ?? 0;
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
            $intervals[] = [$start, $boundaries[$i + 1], $this->statuses->modifiers($active)];
        }

        return $intervals;
    }
}

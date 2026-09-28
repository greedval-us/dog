<?php

namespace App\Modules\Pets\Calculators;

use App\Modules\Pets\Enums\PetState;

/**
 * @phpstan-type Condition array{state: string, operator: string, threshold: int}
 * @phpstan-type Effect array{code: string, kind: string, name: array<string, string>, description: array<string, string>, modifiers: array<string, int>, duration_seconds: int|null, condition_state: string|null, condition_threshold?: int|null, condition_operator?: string, expires_at?: int|null, conditions?: list<Condition>, condition_group?: string|null, condition_priority?: int, care_variants?: list<string>, recovery_actions?: array<string, int>}
 */
final class PetStatusRules
{
    /** @param list<Effect> $effects
     * @return list<Effect>
     */
    public function active(array $effects, int $timestamp): array
    {
        return array_values(array_filter($effects, fn (array $effect): bool => ($effect['expires_at'] ?? 0) > $timestamp));
    }

    /** @param list<Effect> $catalogue
     * @param  array<string, float>  $states
     * @return list<Effect>
     */
    public function conditional(array $catalogue, array $states, string $kind): array
    {
        $matched = [];
        foreach ($catalogue as $effect) {
            if ($effect['kind'] !== $kind || ! $this->conditionsMatch($effect, $states)) {
                continue;
            }

            $key = ($effect['condition_group'] ?? null) === null
                ? 'code:'.$effect['code'] : 'group:'.$effect['condition_group'];
            if (! isset($matched[$key]) || ($effect['condition_priority'] ?? 0) > ($matched[$key]['condition_priority'] ?? 0)) {
                $matched[$key] = [...$effect, 'expires_at' => null, 'duration_seconds' => null];
            }
        }

        return array_values($matched);
    }

    /** @param Effect $effect
     * @param  array<string, float>  $states
     */
    private function conditionsMatch(array $effect, array $states): bool
    {
        $conditions = $effect['conditions'] ?? [];
        if ($effect['condition_state'] !== null) {
            $threshold = $effect['condition_threshold'] ?? null;
            if ($threshold === null) {
                return false;
            }
            $conditions[] = ['state' => $effect['condition_state'], 'operator' => $effect['condition_operator'] ?? 'lt', 'threshold' => $threshold];
        }
        if ($conditions === []) {
            return false;
        }
        foreach ($conditions as $condition) {
            if (! isset($states[$condition['state']]) || ! $this->compare($states[$condition['state']], $condition['operator'], $condition['threshold'])) {
                return false;
            }
        }

        return true;
    }

    private function compare(float $value, string $operator, int $threshold): bool
    {
        if ($threshold < 0 || $threshold > 100) {
            return false;
        }

        return match ($operator) {
            'lt' => $value < $threshold,
            'lte' => $value <= $threshold,
            'gt' => $value > $threshold,
            'gte' => $value >= $threshold,
            default => false,
        };
    }

    /** @param list<Effect> $effects
     * @return array<string, int>
     */
    public function recovery(array $effects, string $variant): array
    {
        $recovery = [];
        foreach ($effects as $effect) {
            $seconds = $effect['recovery_actions'][$variant] ?? 0;
            if ($effect['kind'] === 'debuff' && ($effect['expires_at'] ?? 0) > 0 && $seconds > 0) {
                $recovery[$effect['code']] = min(86400, $seconds);
            }
        }

        return $recovery;
    }

    /** @param list<Effect> $effects
     * @param  array<string, int>  $recovery
     * @return list<Effect>
     */
    public function recover(array $effects, array $recovery, int $timestamp): array
    {
        foreach ($effects as &$effect) {
            if ($effect['kind'] === 'debuff' && isset($effect['expires_at'])) {
                $effect['expires_at'] -= max(0, min(86400, $recovery[$effect['code']] ?? 0));
            }
        }

        return $this->active($effects, $timestamp);
    }

    /** @param list<Effect> $catalogue
     * @param  array<string, float>  $states
     * @param  list<Effect>  $existing
     * @return list<Effect>
     */
    public function current(array $catalogue, array $states, array $existing, int $timestamp, string $kind): array
    {
        $definitions = array_column($catalogue, null, 'code');
        $timed = $this->active($existing, $timestamp);
        foreach ($timed as &$effect) {
            // Older purchased snapshots keep their strength and lifetime, but can use newly introduced remedies.
            $effect['recovery_actions'] ??= $definitions[$effect['code']]['recovery_actions'] ?? [];
        }

        return array_values(array_column([...$timed, ...$this->conditional($catalogue, $states, $kind)], null, 'code'));
    }

    /** @param list<Effect> $existing
     * @param  list<Effect>  $grants
     * @return list<Effect>
     */
    public function award(array $existing, array $grants, int $endedAt, int $timestamp): array
    {
        $effects = array_column($this->active($existing, $timestamp), null, 'code');
        foreach ($grants as $effect) {
            $expiresAt = $endedAt + (int) $effect['duration_seconds'];
            if ($expiresAt > $timestamp && $expiresAt > ($effects[$effect['code']]['expires_at'] ?? 0)) {
                $effects[$effect['code']] = [...$effect, 'expires_at' => $expiresAt];
            }
        }

        return array_values($effects);
    }

    /** @param list<Effect> $effects
     * @return array<string, int>
     */
    public function modifiers(array $effects): array
    {
        $result = array_fill_keys($this->modifierKeys(), 0);
        foreach ($effects as $effect) {
            foreach ($result as $key => $value) {
                $result[$key] += $effect['modifiers'][$key] ?? 0;
            }
        }
        foreach ($result as $key => $value) {
            $result[$key] = max(-50, min(50, $value));
        }

        return $result;
    }

    /** @return list<string> */
    public function modifierKeys(): array
    {
        $keys = ['energy_cost_percent'];
        foreach (PetState::cases() as $state) {
            $keys[] = $state->value.'_gain_percent';
            $keys[] = $state->value.'_loss_percent';
        }

        return $keys;
    }

    public function energyCost(int $base, int $modifier): int
    {
        return $base === 0 ? 0 : max(1, (int) ceil($base * (100 + max(-50, min(50, $modifier))) / 100));
    }

    /** @param array<string, int|float> $effects
     * @param  array<string, int>  $modifiers
     * @return array<string, int|float>
     */
    public function apply(array $effects, array $modifiers): array
    {
        foreach ($effects as $state => $value) {
            $modifier = $modifiers[$state.($value >= 0 ? '_gain_percent' : '_loss_percent')] ?? 0;
            $effects[$state] = round($value * (100 + max(-50, min(50, $modifier))) / 100, 4);
        }

        return $effects;
    }
}

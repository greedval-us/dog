<?php

namespace App\Modules\Pets\Calculators;

use App\Modules\Pets\Enums\PetState;

/** @phpstan-type Effect array{code: string, kind: string, name: array<string, string>, description: array<string, string>, modifiers: array<string, int>, duration_seconds: int|null, condition_state: string|null, condition_threshold?: int|null, condition_operator?: string, expires_at?: int|null} */
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
        return array_values(array_map(fn (array $effect): array => [...$effect, 'expires_at' => null, 'duration_seconds' => null], array_filter(
            $catalogue,
            fn (array $effect): bool => $effect['kind'] === $kind
                && $effect['condition_state'] !== null
                && isset($states[$effect['condition_state']])
                && $this->conditionMatches($effect, $states[$effect['condition_state']]),
        )));
    }

    /** @param Effect $effect */
    private function conditionMatches(array $effect, float $value): bool
    {
        $threshold = $effect['condition_threshold'] ?? null;
        if ($threshold === null || $threshold < 0 || $threshold > 100) {
            return false;
        }

        return match ($effect['condition_operator'] ?? 'lt') {
            'lt' => $value < $threshold,
            'lte' => $value <= $threshold,
            'gt' => $value > $threshold,
            'gte' => $value >= $threshold,
            default => false,
        };
    }

    /** @param list<Effect> $catalogue
     * @param  array<string, float>  $states
     * @param  list<Effect>  $existing
     * @return list<Effect>
     */
    public function current(array $catalogue, array $states, array $existing, int $timestamp, string $kind): array
    {
        return array_values(array_column([...$this->active($existing, $timestamp), ...$this->conditional($catalogue, $states, $kind)], null, 'code'));
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

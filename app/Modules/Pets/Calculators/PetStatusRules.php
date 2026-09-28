<?php

namespace App\Modules\Pets\Calculators;

/** @phpstan-type Effect array{code: string, kind: string, name: array<string, string>, description: array<string, string>, modifiers: array<string, int>, duration_seconds: int|null, condition_state: string|null, condition_below: int|null, expires_at?: int|null} */
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
    public function debuffs(array $catalogue, array $states): array
    {
        return array_values(array_map(fn (array $effect): array => [...$effect, 'expires_at' => null], array_filter(
            $catalogue,
            fn (array $effect): bool => $effect['kind'] === 'debuff'
                && $effect['condition_state'] !== null
                && isset($states[$effect['condition_state']])
                && $states[$effect['condition_state']] < $effect['condition_below'],
        )));
    }

    /** @param list<Effect> $catalogue
     * @param  list<string>  $codes
     * @return list<Effect>
     */
    public function grants(array $catalogue, array $codes): array
    {
        return array_values(array_filter($catalogue, fn (array $effect): bool => $effect['kind'] === 'buff'
            && $effect['duration_seconds'] > 0 && in_array($effect['code'], $codes, true)));
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
     * @return array{energy_cost_percent: int, mood_gain_percent: int}
     */
    public function modifiers(array $effects): array
    {
        $result = ['energy_cost_percent' => 0, 'mood_gain_percent' => 0];
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

    public function energyCost(int $base, int $modifier): int
    {
        return $base === 0 ? 0 : max(1, (int) ceil($base * (100 + max(-50, min(50, $modifier))) / 100));
    }

    /** @param array<string, int|float> $effects
     * @return array<string, int|float>
     */
    public function applyMood(array $effects, int $modifier): array
    {
        if (($effects['mood'] ?? 0) > 0) {
            $effects['mood'] = round($effects['mood'] * (100 + max(-50, min(50, $modifier))) / 100, 4);
        }

        return $effects;
    }
}

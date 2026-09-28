<?php

namespace App\Modules\Pets\Calculators;

/**
 * @phpstan-import-type Effect from PetStatusRules
 *
 * @phpstan-type Risk array{effect: Effect, chance: int, item_name: array<string, string>, quality: int}
 * @phpstan-type Rule array{effect: Effect, chance_percent: int|float, chance_by_quality: array<int, int|float>, duration_seconds: int|null, duration_by_quality: array<int, int>}
 */
final class ItemEffectRules
{
    /** @param list<Rule> $rules
     * @param  array<string, string>  $name
     * @return list<Risk>
     */
    public function forItem(array $rules, int $quality, array $name): array
    {
        $risks = [];
        $quality = max(1, min(10, $quality));
        foreach ($rules as $rule) {
            $effect = $rule['effect'];
            $effect['duration_seconds'] = $rule['duration_by_quality'][$quality] ?? $rule['duration_seconds'] ?? $effect['duration_seconds'];
            if (! in_array($effect['kind'], ['buff', 'debuff'], true) || $effect['condition_state'] !== null || $effect['duration_seconds'] <= 0) {
                continue;
            }
            $percent = $rule['chance_by_quality'][$quality] ?? $rule['chance_percent'];
            $chance = (int) round(max(0, min(100, $percent)) * 100);
            if ($chance > 0) {
                $risks[] = ['effect' => $effect, 'chance' => $chance, 'item_name' => $name, 'quality' => $quality];
            }
        }

        return $risks;
    }

    /** @param list<Risk> $outcomes
     * @return list<Effect>
     */
    public function guaranteed(array $outcomes): array
    {
        return array_values(array_map(fn (array $outcome): array => $outcome['effect'], array_filter(
            $outcomes, fn (array $outcome): bool => $outcome['chance'] === 10000,
        )));
    }

    /** @param list<Risk> $outcomes
     * @return list<Risk>
     */
    public function uncertain(array $outcomes): array
    {
        return array_values(array_filter($outcomes, fn (array $outcome): bool => $outcome['chance'] < 10000));
    }

    /** @param list<Risk> $risks
     * @return list<Risk>
     */
    public function combine(array $risks): array
    {
        $combined = [];
        foreach ($risks as $risk) {
            $code = $risk['effect']['code'];
            if ($risk['chance'] > ($combined[$code]['chance'] ?? 0)
                || ($risk['chance'] === ($combined[$code]['chance'] ?? 0)
                    && $risk['effect']['duration_seconds'] > $combined[$code]['effect']['duration_seconds'])) {
                $combined[$code] = $risk;
            }
        }

        return array_values($combined);
    }
}

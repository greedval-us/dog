<?php

namespace App\Modules\Pets\Calculators;

final class ProgenyRingRules
{
    /** @param array{status:?string,class:?string,grade:?string} $member */
    public function memberReason(array $member, string $system, bool $final): ?string
    {
        if (! in_array($member['status'], ['registered', 'frozen', 'completed'], true)) {
            return 'events.errors.progeny_entry';
        }
        if ($system === 'rkf' && in_array($member['class'], ['baby', 'puppy'], true)) {
            return 'events.errors.progeny_class';
        }
        if ($final || $member['status'] === 'completed') {
            if ($member['status'] !== 'completed') {
                return 'events.errors.progeny_entry';
            }
            $grades = $system === 'rkf' ? ['excellent', 'very_good']
                : ['excellent', 'very_good', 'good', 'satisfactory', 'very_promising', 'promising', 'not_promising'];
            if (! in_array($member['grade'], $grades, true)) {
                return $system === 'rkf' ? 'events.errors.progeny_grade' : 'events.errors.progeny_judging';
            }
        }

        return null;
    }

    /** @param list<array<string,mixed>> $offspring
     * @return array{type:float,uniformity:float,movement:float}
     */
    public function criteria(array $offspring): array
    {
        $averages = [];
        $spread = 0.0;
        foreach (['type', 'structure', 'movement'] as $trait) {
            $values = array_map(static fn (array $child): float => (float) ($child['exterior'][$trait] ?? 65), $offspring);
            $averages[$trait] = array_sum($values) / count($values);
            $spread += array_sum(array_map(static fn (float $value): float => abs($value - $averages[$trait]), $values)) / count($values);
        }

        return ['type' => ($averages['type'] + $averages['structure']) / 2,
            'uniformity' => max(0.0, 100 - $spread / 3 * 2), 'movement' => $averages['movement']];
    }

    /** @param list<array<string,mixed>> $entries
     * @return array{rankings:array<int,array{breed:int,final:?int}>,awards:list<array<string,mixed>>}
     */
    public function placements(array $entries): array
    {
        $eligible = array_values(array_filter($entries, static fn (array $entry): bool => ($entry['result']['qualified'] ?? false) && ! ($entry['result']['eliminated'] ?? false)));
        usort($eligible, static fn (array $first, array $second): int => ($second['result']['score'] <=> $first['result']['score']) ?: ($first['id'] <=> $second['id']));
        $rankings = [];
        $counts = [];
        $finalists = [];
        $awards = [];
        foreach ($eligible as $entry) {
            $breed = $entry['snapshot']['breed_id'];
            $counts[$breed] = ($counts[$breed] ?? 0) + 1;
            $rankings[$entry['id']] = ['breed' => $counts[$breed], 'final' => null];
            if ($counts[$breed] === 1) {
                $finalists[] = $entry;
                $awards[] = ['entry_id' => $entry['id'], 'code' => 'PROGENY_BREED_WINNER', 'kind' => 'comparison', 'details' => ['breed_id' => $breed]];
            }
        }
        foreach (array_slice($finalists, 0, 3) as $index => $entry) {
            $place = $index + 1;
            $rankings[$entry['id']] = ['breed' => 1, 'final' => $place];
            $awards[] = ['entry_id' => $entry['id'], 'code' => 'PROGENY_PLACE_'.$place, 'kind' => 'placement', 'details' => ['place' => $place, 'scope' => 'final']];
            if ($place === 1) {
                $awards[] = ['entry_id' => $entry['id'], 'code' => 'BEST_PROGENY_GROUP', 'kind' => 'comparison', 'details' => ['scope' => 'final']];
            }
        }

        return ['rankings' => $rankings, 'awards' => $awards];
    }
}

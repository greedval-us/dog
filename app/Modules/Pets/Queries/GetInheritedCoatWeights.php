<?php

namespace App\Modules\Pets\Queries;

use App\Models\CoatInheritanceRule;
use App\Models\Pet;

final class GetInheritedCoatWeights
{
    /** @var array<string, array<string, string>> */
    private const Tones = [
        'german_shepherd' => ['black' => 'dark', 'black_tan' => 'dark', 'sable' => 'dark', 'liver' => 'medium'],
        'pit_bull' => ['black' => 'dark', 'brindle' => 'dark', 'fawn' => 'light', 'blue' => 'medium'],
        'dachshund' => ['black_tan' => 'dark', 'chocolate_tan' => 'dark', 'red' => 'medium', 'cream' => 'light'],
    ];

    /** @return array<string, int> */
    public function handle(Pet $father, Pet $mother): array
    {
        if ($father->dog_id !== $mother->dog_id) {
            return [];
        }

        $catalog = $father->dog->coat_colors;

        if (! array_key_exists($father->coat_color, $catalog) || ! array_key_exists($mother->coat_color, $catalog)) {
            return [];
        }

        $parentColors = [$father->coat_color, $mother->coat_color];
        sort($parentColors, SORT_STRING);
        $rules = CoatInheritanceRule::query()->where('dog_id', $father->dog_id)
            ->where('first_color', $parentColors[0])->where('second_color', $parentColors[1])
            ->orderBy('offspring_color')->get();
        $baseWeights = [];

        foreach ($rules as $rule) {
            if ($rule->weight < 1 || ! array_key_exists($rule->offspring_color, $catalog)) {
                return [];
            }

            $baseWeights[$rule->offspring_color] = $rule->weight;
        }

        if ($baseWeights === []) {
            return [];
        }

        [$colorCounts, $toneCounts, $total] = $this->ancestry($father, $mother);
        $tones = self::Tones[$father->dog->breed] ?? [];
        $weighted = [];

        foreach ($baseWeights as $color => $weight) {
            $colorFraction = $total > 0 ? ($colorCounts[$color] ?? 0) / $total : 0;
            $toneFraction = $total > 0 && isset($tones[$color]) ? ($toneCounts[$tones[$color]] ?? 0) / $total : 0;
            $weighted[$color] = $weight * (1 + 2 * $colorFraction + 2 * $toneFraction);
        }

        return $this->normalize($weighted);
    }

    /** @return array{array<string, float>, array<string, float>, float} */
    private function ancestry(Pet $father, Pet $mother): array
    {
        $tones = self::Tones[$father->dog->breed] ?? [];
        $colors = [];
        $toneCounts = [];
        $total = 0.0;
        $nodes = [
            ['pet' => $father, 'path' => [$father->id]],
            ['pet' => $mother, 'path' => [$mother->id]],
        ];

        for ($depth = 1; $depth <= 3; $depth++) {
            $weight = 1 / (2 ** ($depth - 1));
            $next = [];

            foreach ($nodes as $node) {
                $pet = $node['pet'];
                $colors[$pet->coat_color] = ($colors[$pet->coat_color] ?? 0.0) + $weight;
                $total += $weight;

                if (isset($tones[$pet->coat_color])) {
                    $tone = $tones[$pet->coat_color];
                    $toneCounts[$tone] = ($toneCounts[$tone] ?? 0.0) + $weight;
                }

                if ($depth < 3) {
                    foreach (array_filter([$pet->father_id, $pet->mother_id]) as $id) {
                        if (! in_array($id, $node['path'], true)) {
                            $next[] = ['id' => $id, 'path' => [...$node['path'], $id]];
                        }
                    }
                }
            }

            if ($next === []) {
                break;
            }

            $parents = Pet::query()->whereIn('id', array_unique(array_column($next, 'id')))
                ->get(['id', 'coat_color', 'father_id', 'mother_id'])->keyBy('id');
            $nodes = [];

            foreach ($next as $node) {
                $pet = $parents->get($node['id']);

                if ($pet !== null) {
                    $nodes[] = ['pet' => $pet, 'path' => $node['path']];
                }
            }
        }

        return [$colors, $toneCounts, $total];
    }

    /**
     * @param  array<string, int|float>  $weights
     * @return array<string, int>
     */
    private function normalize(array $weights): array
    {
        $total = array_sum($weights);
        $normalized = [];
        $fractions = [];

        foreach ($weights as $color => $weight) {
            $exact = $weight / $total * 100000;
            $normalized[$color] = max(1, (int) floor($exact));
            $fractions[$color] = $exact - $normalized[$color];
        }

        $remaining = 100000 - array_sum($normalized);
        $colors = array_keys($normalized);
        usort($colors, static fn (string $first, string $second): int => ($fractions[$second] <=> $fractions[$first]) ?: strcmp($first, $second));

        for ($index = 0; $remaining > 0; $index++, $remaining--) {
            $normalized[$colors[$index % count($colors)]]++;
        }

        if ($remaining < 0) {
            $largest = $normalized;
            arsort($largest, SORT_NUMERIC);

            foreach ($largest as $color => $weight) {
                $reduction = min(-$remaining, $weight - 1);
                $normalized[$color] -= $reduction;
                $remaining += $reduction;

                if ($remaining === 0) {
                    break;
                }
            }
        }

        return $normalized;
    }
}

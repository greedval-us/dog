<?php

namespace App\Modules\Pets\Calculators;

use App\Modules\Pets\Enums\DogSize;

/**
 * @phpstan-type VariantBalance array{duration: int, cooldown: int, energy: int, items: array<string, int>, effects: array<string, int>}
 * @phpstan-type CareBalance array{feeding_by_size: array<string, int>, minimum_needs: array<string, array<string, int>>, quality_bonuses: array<string, array{state: string, per_level: int, base_quality: int, max_quality: int}>, options: array<string, VariantBalance>}
 * @phpstan-type CareOption array{group: string, label: string, duration: int, cooldown: int, energy: int, requirements: list<string>, uses: array<string, int>, effects: array<string, int>}
 */
final class PetCareRules
{
    /** @param CareBalance $balance */
    public function __construct(private array $balance) {}

    /** @return array<string, CareOption> */
    public function options(DogSize $size): array
    {
        $variants = [
            'meal' => ['feed', 'A portion of food'],
            'water' => ['feed', 'Fresh water'],
            'walk' => ['walk', 'Walk outside'],
            'home' => ['walk', 'Move around at home'],
            'attention' => ['play', 'Play together'],
            'toy' => ['play', 'Play with a toy'],
            'wash' => ['groom', 'Rinse paws'],
            'care' => ['groom', 'Use a care product'],
            'nap' => ['sleep', 'Take a nap'],
            'sleep' => ['sleep', 'Long sleep'],
        ];
        $options = [];

        foreach ($variants as $id => [$group, $label]) {
            $settings = $this->balance['options'][$id];

            if ($id === 'meal') {
                $settings['effects']['satiety'] = $this->balance['feeding_by_size'][$size->value];
            }

            $options[$id] = [
                'group' => $group, 'label' => $label,
                'duration' => $settings['duration'], 'cooldown' => $settings['cooldown'],
                'energy' => $settings['energy'], 'requirements' => array_keys($settings['items']),
                'uses' => $settings['items'], 'effects' => $settings['effects'],
            ];
        }

        return $options;
    }

    /** @param CareOption $option
     * @param  array<string, float>  $states
     */
    public function unavailableReason(array $option, array $states, float $energy): ?string
    {
        if ($energy < $option['energy']) {
            return 'Not enough energy. Let your dog rest first.';
        }

        foreach ($this->balance['minimum_needs'][$option['group']] ?? [] as $state => $minimum) {
            if ($states[$state] < $minimum) {
                return 'Feed your dog and offer water before active play or a walk.';
            }
        }

        $primary = match ($option['group']) {
            'feed' => isset($option['effects']['satiety']) ? 'satiety' : 'hydration',
            'groom' => 'cleanliness',
            'sleep' => 'energy',
            default => null,
        };

        return $primary !== null && $states[$primary] >= 100 ? 'This need is already full. Choose another action.' : null;
    }

    /** @param CareOption $option
     * @param  array<string, int>  $qualities
     * @return array<string, int>
     */
    public function effects(array $option, array $qualities): array
    {
        $effects = $option['effects'];

        foreach ($qualities as $category => $quality) {
            foreach ($this->qualityBonus($category, $quality) as $state => $bonus) {
                $effects[$state] = ($effects[$state] ?? 0) + $bonus;
            }
        }

        return $effects;
    }

    /** @return array<string, int> */
    public function qualityBonus(string $category, int $quality): array
    {
        $bonus = $this->balance['quality_bonuses'][$category] ?? null;

        return $bonus === null ? [] : [
            $bonus['state'] => max(0, min($bonus['max_quality'], $quality) - $bonus['base_quality']) * $bonus['per_level'],
        ];
    }
}

<?php

namespace App\Modules\Pets\Calculators;

use App\Modules\Pets\DTO\CareOption;
use App\Modules\Pets\Enums\CareRefusal;
use App\Modules\Pets\Enums\DogSize;
use App\Modules\Pets\Enums\PetActivity;

/**
 * @phpstan-type VariantBalance array{duration: int, cooldown: int, energy: int, items: array<string, int>, effects: array<string, int>}
 * @phpstan-type CareBalance array{feeding_by_size: array<string, int>, minimum_needs: array<string, array<string, int>>, quality_bonuses: array<string, array{state: string, per_level: int, base_quality: int, max_quality: int}>, options: array<string, VariantBalance>}
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

            $options[$id] = new CareOption(
                group: PetActivity::from($group), label: $label,
                duration: $settings['duration'], cooldown: $settings['cooldown'],
                energy: $settings['energy'], requirements: array_keys($settings['items']),
                uses: $settings['items'], effects: $settings['effects'],
                optional: match ($id) {
                    'walk' => ['clothing'], 'toy' => ['sports'], default => []
                },
            );
        }

        return $options;
    }

    /** @param array<string, float> $states */
    public function unavailableCode(CareOption $option, array $states, float $energy, bool $hasStatusRecovery = false): ?CareRefusal
    {
        if ($option->group === PetActivity::Training && min($states['health'], $states['satiety'], $states['hydration']) < 50) {
            return CareRefusal::TrainingNeeds;
        }

        if ($energy < $option->energy) {
            return CareRefusal::Energy;
        }

        foreach ($this->balance['minimum_needs'][$option->group->value] ?? [] as $state => $minimum) {
            if ($states[$state] < $minimum) {
                return CareRefusal::ActiveNeeds;
            }
        }

        $primary = match ($option->group) {
            PetActivity::Feed => isset($option->effects['satiety']) ? 'satiety' : 'hydration',
            PetActivity::Groom => 'cleanliness',
            PetActivity::Sleep => 'energy',
            default => null,
        };

        if ($option->group === PetActivity::Sleep && $states['health'] < 100) {
            return null;
        }

        return $primary !== null && $states[$primary] >= 100 && ! $hasStatusRecovery ? CareRefusal::NeedFull : null;
    }

    /** @param array<string, float> $states */
    public function unavailableReason(CareOption $option, array $states, float $energy, bool $hasStatusRecovery = false): ?string
    {
        return $this->unavailableCode($option, $states, $energy, $hasStatusRecovery)?->message();
    }

    /** @param array<string, int> $qualities
     * @param  array<string, int>  $bonuses
     * @return array<string, int>
     */
    public function effects(CareOption $option, array $qualities, array $bonuses = []): array
    {
        $effects = $option->effects;

        foreach ($qualities as $category => $quality) {
            foreach ($this->qualityBonus($category, $quality) as $state => $bonus) {
                $effects[$state] = ($effects[$state] ?? 0) + $bonus;
            }
        }

        foreach ($bonuses as $state => $bonus) {
            if (in_array($state, ['health', 'energy', 'satiety', 'hydration', 'mood', 'cleanliness', 'bond'], true)) {
                $effects[$state] = ($effects[$state] ?? 0) + max(0, min(30, $bonus));
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

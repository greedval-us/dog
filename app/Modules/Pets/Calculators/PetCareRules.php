<?php

namespace App\Modules\Pets\Calculators;

use App\Modules\Pets\Enums\DogSize;

/** @phpstan-type CareOption array{group: string, label: string, description: string, duration: int, cooldown: int, energy: int, requirements: list<string>, effects: array<string, int>} */
final class PetCareRules
{
    /** @return array<string, CareOption> */
    public function options(DogSize $size): array
    {
        $satiety = match ($size) {
            DogSize::Small => 30,
            DogSize::Medium => 22,
            DogSize::Large => 15,
        };

        return [
            'meal' => $this->option('feed', 'A portion of food', 'One portion from the selected food package.', 30, 300, 0, ['food'], ['satiety' => $satiety]),
            'water' => $this->option('feed', 'Fresh water', 'Refill the bowl for free. Shares the feeding cooldown.', 15, 300, 0, [], ['hydration' => 35]),
            'walk' => $this->option('walk', 'Walk outside', 'Requires a collar and leash. Each loses one use.', 300, 900, 12, ['collars', 'leashes'], ['mood' => 25, 'bond' => 4, 'satiety' => -8, 'hydration' => -10, 'cleanliness' => -12]),
            'home' => $this->option('walk', 'Move around at home', 'A safe alternative without equipment, with a smaller benefit.', 120, 900, 4, [], ['mood' => 8, 'bond' => 1, 'satiety' => -3, 'hydration' => -3]),
            'attention' => $this->option('play', 'Play together', 'Spend time together without a toy.', 120, 600, 6, [], ['mood' => 10, 'bond' => 2, 'satiety' => -3, 'hydration' => -3]),
            'toy' => $this->option('play', 'Play with a toy', 'A better quality toy improves the mood bonus. Uses one charge.', 180, 600, 10, ['toys'], ['mood' => 18, 'bond' => 4, 'satiety' => -5, 'hydration' => -6]),
            'wash' => $this->option('groom', 'Rinse paws', 'Basic care with water, without supplies.', 60, 1200, 0, [], ['cleanliness' => 10, 'bond' => 1]),
            'care' => $this->option('groom', 'Use a care product', 'Quality improves cleaning. Uses one charge of the selected product.', 180, 1200, 0, ['care'], ['cleanliness' => 25, 'bond' => 3]),
            'nap' => $this->option('sleep', 'Take a nap', 'A short rest restores some energy.', 300, 1800, 0, [], ['energy' => 25, 'satiety' => -4, 'hydration' => -4]),
            'sleep' => $this->option('sleep', 'Long sleep', 'More energy, but a longer wait and more hunger.', 1200, 1800, 0, [], ['energy' => 70, 'satiety' => -12, 'hydration' => -12]),
        ];
    }

    /** @param CareOption $option
     * @param  array<string, float>  $states
     */
    public function unavailableReason(array $option, array $states, float $energy): ?string
    {
        if ($energy < $option['energy']) {
            return 'Not enough energy. Let your dog rest first.';
        }

        if (in_array($option['group'], ['walk', 'play'], true) && ($states['satiety'] < 10 || $states['hydration'] < 10)) {
            return 'Feed your dog and offer water before active play or a walk.';
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

        foreach (['toys' => 'mood', 'care' => 'cleanliness'] as $category => $state) {
            if (isset($qualities[$category])) {
                $effects[$state] += max(0, min(10, $qualities[$category]) - 1);
            }
        }

        return $effects;
    }

    /** @param list<string> $requirements
     * @param  array<string, int>  $effects
     * @return CareOption
     */
    private function option(string $group, string $label, string $description, int $duration, int $cooldown, int $energy, array $requirements, array $effects): array
    {
        return compact('group', 'label', 'description', 'duration', 'cooldown', 'energy', 'requirements', 'effects');
    }
}

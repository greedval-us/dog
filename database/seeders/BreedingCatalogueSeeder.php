<?php

namespace Database\Seeders;

use App\Models\BreedingPartner;
use App\Models\CoatInheritanceRule;
use App\Models\Dog;
use App\Modules\Pets\DTO\NewPetData;
use App\Modules\Pets\Enums\PetSex;
use App\Modules\Pets\Enums\PetStat;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BreedingCatalogueSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $rareColors = [
                'german_shepherd' => ['liver', ['ru' => 'Печёночный', 'en' => 'Liver']],
                'pit_bull' => ['blue', ['ru' => 'Голубой', 'en' => 'Blue']],
                'dachshund' => ['cream', ['ru' => 'Кремовый', 'en' => 'Cream']],
            ];

            foreach (Dog::query()->whereIn('breed', array_keys($rareColors))->get() as $dog) {
                [$rareColor, $rareNames] = $rareColors[$dog->breed];
                $colors = $dog->coat_colors;
                $colors[$rareColor] ??= $rareNames;
                $dog->update(['coat_colors' => $colors]);
                $this->seedCoatRules($dog, $rareColor);
                $this->seedPartners($dog, $rareColor);
            }
        });
    }

    private function seedCoatRules(Dog $dog, string $rareColor): void
    {
        $colors = array_keys($dog->coat_colors);
        sort($colors, SORT_STRING);
        $commonColors = array_values(array_diff($colors, [$rareColor]));

        if ($commonColors === []) {
            return;
        }

        foreach ($colors as $firstIndex => $firstColor) {
            foreach (array_slice($colors, $firstIndex) as $secondColor) {
                if ($firstColor === $rareColor && $secondColor === $rareColor) {
                    $weights = $this->distribute([$rareColor => 1000], $commonColors, 9000);
                } elseif ($firstColor === $rareColor || $secondColor === $rareColor) {
                    $commonColor = $firstColor === $rareColor ? $secondColor : $firstColor;
                    $otherColors = array_values(array_diff($commonColors, [$commonColor]));
                    $weights = $this->distribute([$commonColor => 8000, $rareColor => 200], $otherColors ?: [$commonColor], 1800);
                } elseif ($firstColor === $secondColor) {
                    $otherColors = array_values(array_diff($commonColors, [$firstColor]));
                    $weights = $this->distribute([$firstColor => 8000, $rareColor => 10], $otherColors ?: [$firstColor], 1990);
                } else {
                    $otherColors = array_values(array_diff($commonColors, [$firstColor, $secondColor]));
                    $weights = $this->distribute([$firstColor => 4500, $secondColor => 4500, $rareColor => 10], $otherColors ?: [$firstColor, $secondColor], 990);
                }

                CoatInheritanceRule::query()->where('dog_id', $dog->id)
                    ->where('first_color', $firstColor)->where('second_color', $secondColor)
                    ->whereNotIn('offspring_color', array_keys($weights))->delete();

                foreach ($weights as $offspringColor => $weight) {
                    CoatInheritanceRule::query()->updateOrCreate([
                        'dog_id' => $dog->id,
                        'first_color' => $firstColor,
                        'second_color' => $secondColor,
                        'offspring_color' => $offspringColor,
                    ], ['weight' => $weight]);
                }
            }
        }
    }

    /**
     * @param  array<string, int>  $weights
     * @param  list<string>  $colors
     * @return array<string, int>
     */
    private function distribute(array $weights, array $colors, int $total): array
    {
        $share = intdiv($total, count($colors));
        $remainder = $total % count($colors);

        foreach ($colors as $index => $color) {
            $weights[$color] = ($weights[$color] ?? 0) + $share + (int) ($index < $remainder);
        }

        return $weights;
    }

    private function seedPartners(Dog $dog, string $rareColor): void
    {
        $commonColors = array_values(array_diff(array_keys($dog->coat_colors), [$rareColor]));

        if ($commonColors === []) {
            return;
        }

        foreach (PetSex::cases() as $sex) {
            $code = 'system_'.$dog->breed.'_'.$sex->value;

            if (BreedingPartner::query()->where('code', $code)->exists()) {
                continue;
            }

            $pet = $dog->newPet(new NewPetData(
                name: $dog->localizedName('ru').' · '.($sex === PetSex::Male ? 'Север' : 'Луна'),
                sex: $sex,
                coatColor: $commonColors[0],
            ));

            foreach (PetStat::cases() as $stat) {
                $pet->setAttribute($stat->value, max(1, (int) round($pet->getAttribute($stat->potentialColumn()) * 0.7)));
            }

            $pet->forceFill(['user_id' => null, 'born_at' => now()->subDays(config('doglive.breeding_minimum_age_days', 7))])->save();
            BreedingPartner::query()->create(['pet_id' => $pet->id, 'code' => $code, 'price' => 100, 'is_active' => true]);
        }
    }
}

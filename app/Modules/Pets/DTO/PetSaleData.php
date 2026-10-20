<?php

namespace App\Modules\Pets\DTO;

use App\Models\PetSaleListing;

/**
 * @phpstan-type Card array{id:int, price:int, pet:array{id:int, name:string, breed:string, sex:string, bornAt:string, ageMonths:int, retired:bool, titles:list<array{name:string, discipline:string, frequency:string, awardedAt:string}>}, seller:array{id:int, username:string}}
 */
final readonly class PetSaleData
{
    /** @return Card */
    public static function fromModel(PetSaleListing $listing, string $locale): array
    {
        if ($listing->user === null) {
            throw new \InvalidArgumentException('A pet sale card requires a seller.');
        }
        $pet = $listing->pet;

        return [
            'id' => $listing->id,
            'price' => $listing->price,
            'pet' => [
                'id' => $pet->id, 'name' => $pet->name, 'breed' => $pet->dog->localizedName($locale),
                'sex' => $pet->sex->value, 'bornAt' => $pet->born_at->toIso8601String(),
                'ageMonths' => $pet->ageMonthsAt(now()), 'retired' => $pet->retired_at !== null,
                'titles' => PetTitleData::fromPet($pet, $locale),
            ],
            'seller' => ['id' => $listing->user->id, 'username' => $listing->user->username],
        ];
    }
}

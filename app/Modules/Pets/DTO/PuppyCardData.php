<?php

namespace App\Modules\Pets\DTO;

use App\Models\Puppy;
use App\Modules\Pets\Enums\PetStat;

/**
 * @phpstan-type Card array{id: int, name: string, breed: string, breedCode: string, illustration: string|null, sex: string, coatColor: string, coatLabel: string, generation: int, potentials: array<string, int>, status: string, price: int|null, expiresAt: string, seller: array{name: string, username: string}|null}
 */
final readonly class PuppyCardData
{
    /** @return Card */
    public static function fromModel(Puppy $puppy, string $locale): array
    {
        $potentials = [];
        foreach (PetStat::cases() as $stat) {
            $potentials[$stat->value] = $puppy->getAttribute($stat->potentialColumn());
        }
        $coat = $puppy->dog->coat_colors[$puppy->coat_color] ?? [];
        $seller = $puppy->user;

        return [
            'id' => $puppy->id,
            'name' => $puppy->name,
            'breed' => $puppy->dog->localizedName($locale),
            'breedCode' => $puppy->dog->breed,
            'illustration' => $puppy->dog->illustration(),
            'sex' => $puppy->sex->value,
            'coatColor' => $puppy->coat_color,
            'coatLabel' => $coat[$locale] ?? $coat['en'] ?? $puppy->coat_color,
            'generation' => $puppy->generation,
            'potentials' => $potentials,
            'status' => $puppy->status,
            'price' => $puppy->status === 'kennel' ? (int) config('doglive.kennel_price') : $puppy->sale_price,
            'expiresAt' => $puppy->expires_at->toIso8601String(),
            'seller' => $seller === null ? null : ['name' => $seller->name, 'username' => $seller->username],
        ];
    }
}

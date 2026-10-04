<?php

namespace App\Modules\Pets\DTO;

use App\Models\Pet;
use App\Models\PetTitle;

final readonly class PetTitleData
{
    /** @return list<array{name: string, discipline: string, frequency: string, awardedAt: string}> */
    public static function fromPet(Pet $pet, string $locale): array
    {
        if (! $pet->relationLoaded('titles')) {
            return [];
        }

        return array_values($pet->titles->map(fn (PetTitle $title): array => [
            'name' => __('events.titles.'.$title->frequency, ['discipline' => __('events.disciplines.'.$title->discipline, [], $locale)], $locale),
            'discipline' => $title->discipline,
            'frequency' => $title->frequency,
            'awardedAt' => $title->awarded_at->toIso8601String(),
        ])->all());
    }
}

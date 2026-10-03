<?php

namespace App\Modules\Pets\DTO;

use App\Models\CharacterTrait;
use App\Models\Pet;
use App\Modules\Pets\Enums\PetStat;

/**
 * @phpstan-type Summary array{id: int, name: string, breed: string, sex: string, coatColor: string, generation: int, status: string, hasPedigree: bool}
 * @phpstan-type Profile array{id: int, name: string, breed: string, sex: string, size: string, coatColor: string, generation: int, description: string|null, bornAt: string, isPurebred: bool, traits: list<string>, stats: array<string, array{value: int, potential: int}>, lifecycle: array{status: string, archivedAt: string|null}, hasPedigree: bool}
 */
final readonly class PublicPetProfileData
{
    /** @return Profile */
    public static function fromModel(Pet $pet, string $locale): array
    {
        $stats = [];

        foreach (PetStat::cases() as $stat) {
            $stats[$stat->value] = [
                'value' => $pet->getAttribute($stat->value),
                'potential' => $pet->getAttribute($stat->potentialColumn()),
            ];
        }

        return [
            'id' => $pet->id,
            'name' => $pet->name,
            'breed' => $pet->dog->localizedName($locale),
            'sex' => $pet->sex->value,
            'size' => $pet->size->value,
            'coatColor' => self::coatColor($pet, $locale),
            'generation' => $pet->generation,
            'description' => $pet->description,
            'bornAt' => $pet->born_at->toIso8601String(),
            'isPurebred' => $pet->is_purebred,
            'traits' => array_values($pet->characterTraits->map(fn (CharacterTrait $trait): string => $trait->code)->all()),
            'stats' => $stats,
            'lifecycle' => [
                'status' => self::status($pet),
                'archivedAt' => $pet->archivedAt()?->toIso8601String(),
            ],
            'hasPedigree' => self::hasPedigree($pet),
        ];
    }

    /** @return Summary */
    public static function summary(Pet $pet, string $locale): array
    {
        return [
            'id' => $pet->id,
            'name' => $pet->name,
            'breed' => $pet->dog->localizedName($locale),
            'sex' => $pet->sex->value,
            'coatColor' => self::coatColor($pet, $locale),
            'generation' => $pet->generation,
            'status' => self::status($pet),
            'hasPedigree' => self::hasPedigree($pet),
        ];
    }

    private static function status(Pet $pet): string
    {
        return $pet->died_at !== null ? 'deceased' : ($pet->retired_at !== null ? 'retired' : 'active');
    }

    private static function coatColor(Pet $pet, string $locale): string
    {
        return $pet->dog->coat_colors[$pet->coat_color][$locale] ?? $pet->dog->coat_colors[$pet->coat_color]['en'] ?? $pet->coat_color;
    }

    private static function hasPedigree(Pet $pet): bool
    {
        return ($pet->father_id !== null && $pet->father_id !== $pet->id)
            || ($pet->mother_id !== null && $pet->mother_id !== $pet->id);
    }
}

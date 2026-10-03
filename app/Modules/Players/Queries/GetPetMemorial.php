<?php

namespace App\Modules\Players\Queries;

use App\Models\GameAsset;
use App\Models\Pet;
use App\Models\Skill;
use App\Models\User;
use App\Modules\Appearance\DTO\AppearanceAssetData;
use App\Modules\Appearance\DTO\PetAppearanceData;
use App\Modules\Pets\DTO\PetProfileData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * @phpstan-type MemorialPet array{id: int, name: string, breed: string, portraitId: int|null, backgroundId: int|null, bornAt: string, archivedAt: string, status: 'retired'|'deceased'}
 * @phpstan-type LearnedSkill array{id: int, name: string, description: string, level: int}
 */
final class GetPetMemorial
{
    /** @return array{data: list<MemorialPet>, nextCursor: string|null, previousCursor: string|null} */
    public function handle(User $user, string $locale, int $perPage = 12): array
    {
        if ($perPage < 1 || $perPage > 100) {
            throw new InvalidArgumentException('Page size must be between 1 and 100.');
        }

        $page = $user->pets()->where(fn (Builder $query) => $query->whereNotNull('retired_at')->orWhereNotNull('died_at'))
            ->with('dog')->orderByDesc('id')->cursorPaginate($perPage);
        $pets = $page->getCollection();
        $appearance = $this->appearance($pets, $user, $locale);

        return [
            'data' => array_values($pets->map(function (Pet $pet) use ($appearance, $locale): array {
                $archivedAt = $pet->died_at ?? $pet->retired_at;
                assert($archivedAt !== null);

                return [
                    'id' => $pet->id,
                    'name' => $pet->name,
                    'breed' => $pet->dog->localizedName($locale),
                    'portraitId' => $appearance[$pet->id]->portraitId,
                    'backgroundId' => $appearance[$pet->id]->backgroundId,
                    'bornAt' => $pet->born_at->toIso8601String(),
                    'archivedAt' => $archivedAt->toIso8601String(),
                    'status' => $pet->died_at !== null ? 'deceased' : 'retired',
                ];
            })->all()),
            'nextCursor' => $page->nextCursor()?->encode(),
            'previousCursor' => $page->previousCursor()?->encode(),
        ];
    }

    /** @return array{pet: PetProfileData, appearance: PetAppearanceData, learnedSkills: list<LearnedSkill>} */
    public function pet(User $user, int $petId, string $locale): array
    {
        $pet = $user->pets()->where(fn (Builder $query) => $query->whereNotNull('retired_at')->orWhereNotNull('died_at'))
            ->with(['dog', 'characterTraits', 'skills'])->findOrFail($petId);
        $appearance = $this->appearance(new Collection([$pet]), $user, $locale)[$pet->id];

        return [
            'pet' => PetProfileData::fromModel($pet, $pet->dog, $locale),
            'appearance' => new PetAppearanceData([], $appearance->portraitId, $appearance->backgroundId),
            'learnedSkills' => array_values($pet->skills->filter(fn (Skill $skill): bool => $skill->pivot->level > 0)
                ->map(fn (Skill $skill): array => [
                    'id' => $skill->id,
                    'name' => $skill->name[$locale] ?? $skill->name['en'] ?? $skill->code,
                    'description' => $skill->description[$locale] ?? $skill->description['en'] ?? '',
                    'level' => $skill->pivot->level,
                ])->all()),
        ];
    }

    /**
     * @param  Collection<int, Pet>  $pets
     * @return array<int, PetAppearanceData>
     */
    private function appearance(Collection $pets, User $user, string $locale): array
    {
        if ($pets->isEmpty()) {
            return [];
        }

        $assets = GameAsset::query()->where('is_active', true)
            ->where(function (Builder $query) use ($pets): void {
                foreach ($pets as $pet) {
                    $query->orWhere(fn (Builder $query) => $query->compatibleWith($pet));
                }
            })
            ->withExists(['unlocks as is_unlocked' => fn (Builder $query) => $query->whereBelongsTo($user)])
            ->orderBy('sort_order')->orderBy('id')->get();
        $catalogue = AppearanceAssetData::fromCatalogue($assets, $locale);

        return $pets->mapWithKeys(function (Pet $pet) use ($assets, $catalogue): array {
            $compatible = $assets->filter(fn (GameAsset $asset): bool => isset($catalogue[$asset->id]) && $asset->matches($pet))
                ->map(fn (GameAsset $asset): AppearanceAssetData => $catalogue[$asset->id]);

            return [$pet->id => PetAppearanceData::fromAssets($pet, array_values($compatible->all()))];
        })->all();
    }
}

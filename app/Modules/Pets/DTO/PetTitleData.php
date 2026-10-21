<?php

namespace App\Modules\Pets\DTO;

use App\Models\Pet;
use App\Models\PetTitle;

/** @phpstan-type Title array{name:string,discipline:string,frequency:string,awardedAt:string,code:string,classKey:string|null,rank:int|null,ageMonths:int|null,breedId:int|null,kind:string,scopeKey:string,sourceCount:int,context:array<string,mixed>,details:array<string,mixed>,regulationVersion:string|null} */
final readonly class PetTitleData
{
    /** @return list<Title> */
    public static function fromPet(Pet $pet, string $locale): array
    {
        if (! $pet->relationLoaded('titles')) {
            return [];
        }

        return array_values($pet->titles->map(fn (PetTitle $title): array => self::fromTitle($title, $locale))->all());
    }

    /** @return Title */
    public static function fromTitle(PetTitle $title, string $locale): array
    {
        $name = match ($title->code) {
            'conformation_best_male', 'conformation_best_female', 'conformation_best_of_breed' => __('events.awards.'.$title->code, [], $locale),
            'conformation_class_place_1', 'conformation_class_place_2', 'conformation_class_place_3' => __('events.awards.class_place', ['rank' => $title->rank, 'class' => __('events.classes.'.$title->class_key, [], $locale)], $locale),
            default => __('events.titles.'.$title->frequency, ['discipline' => __('events.disciplines.'.$title->discipline, [], $locale)], $locale),
        };
        if ($title->kind !== 'legacy') {
            $key = 'events.regulated_awards.'.$title->code;
            $translated = __($key, [], $locale);
            $name = $translated === $key ? $title->code : $translated;
            if (preg_match('/^NW_CATEGORY_[A-Z]+_PLACE_([1-3])$/', $title->code, $match) === 1) {
                $name = __('events.category_place', ['rank' => $match[1]], $locale);
            }
            $context = [];
            if ($title->class_key !== null) {
                $context[] = self::contextLabel('regulations.classes', $title->class_key, $locale);
            }
            $details = $title->details ?? [];
            foreach (['program' => 'programs', 'odor' => 'odors', 'category' => 'standings'] as $field => $group) {
                if (is_string($details[$field] ?? null) && $details[$field] !== '') {
                    $context[] = self::contextLabel($group, $details[$field], $locale);
                }
            }
            if ($title->discipline === 'agility') {
                foreach (['format' => 'formats', 'heightCategory' => 'height_categories'] as $field => $group) {
                    if (is_string($details[$field] ?? null) && $details[$field] !== '') {
                        $context[] = self::contextLabel($group, $details[$field], $locale);
                    }
                }
            }
            if ($title->discipline === 'canicross' && is_scalar($details['race_category'] ?? null)) {
                $context[] = self::contextLabel('race_categories', (string) $details['race_category'], $locale);
            }
            if ($title->code === 'SHOW_GRADE' && is_string($details['grade'] ?? null)) {
                $context[] = self::contextLabel('grades', $details['grade'], $locale);
            }
            if (($title->code === 'SPORT_REGIONAL_CH' || str_starts_with($title->code, 'REG_')) && is_string($details['region'] ?? null) && $details['region'] !== '') {
                $context[] = self::contextLabel('regions', $details['region'], $locale);
            }
            if (is_int($details['showYear'] ?? null)) {
                $context[] = __('events.game_year', ['year' => $details['showYear']], $locale);
            }
            if (is_string($details['showEdition'] ?? null) && $details['showEdition'] !== '') {
                $context[] = __('events.game_edition', ['edition' => $details['showEdition']], $locale);
            }
            if ($context !== []) {
                $name .= ' · '.implode(' · ', $context);
            }
        }

        return [
            'name' => $name,
            'discipline' => $title->discipline,
            'frequency' => $title->frequency,
            'awardedAt' => $title->awarded_at->toIso8601String(),
            'code' => $title->code, 'classKey' => $title->class_key, 'rank' => $title->rank,
            'ageMonths' => $title->age_months, 'breedId' => $title->breed_id,
            'kind' => $title->kind, 'context' => $title->details, 'details' => $title->details,
            'scopeKey' => $title->scope_key,
            'sourceCount' => self::sourceCount($title->details),
            'regulationVersion' => $title->regulation_version,
        ];
    }

    /** @param array<string,mixed> $details */
    private static function sourceCount(array $details): int
    {
        $sources = $details['credential_title_ids'] ?? $details['credential_entry_ids'] ?? [];

        return is_array($sources) ? count(array_unique(array_filter($sources, is_int(...)))) : 0;
    }

    private static function contextLabel(string $group, string $value, string $locale): string
    {
        $key = 'events.'.$group.'.'.$value;
        $translated = __($key, [], $locale);

        return $translated === $key ? $value : $translated;
    }
}

<?php

namespace App\Modules\Pets\DTO;

use App\Models\Dog;

final readonly class GameEventDivisionData
{
    public static function label(string $division, string $locale, ?Dog $breed = null): string
    {
        [$tier, $category, $sex] = array_pad(explode(':', self::base($division), 3), 3, null);
        $category ??= 'all';
        if (in_array($tier, ['agility_1', 'agility_2', 'agility_3'], true)) {
            $label = __('events.regulations.classes.'.$tier, [], $locale)
                .' · '.__('events.height_categories.'.$category, [], $locale);
        } elseif (in_array($tier, ['nw_0', 'nw_1', 'nw_2', 'nw_3', 'nw_v', 'nw_test'], true)) {
            $label = __('events.regulations.classes.'.$tier, [], $locale)
                .' · '.__('events.programs.'.$category, [], $locale);
            if ($tier === 'nw_test' && $sex !== null) {
                $label .= ' · '.__('events.odors.'.$sex, [], $locale);
            }
        } elseif ($tier === 'canicross_open') {
            $label = __('events.regulations.classes.'.$tier, [], $locale)
                .' · '.(str_starts_with($category, 'breed-')
                    ? ($breed?->localizedName($locale) ?? __('events.sport_scopes.breed', [], $locale))
                    : __('events.race_categories.'.$category, [], $locale));
            if ($sex !== null) {
                $label .= ' · '.__('events.handler_categories.'.$sex, [], $locale);
            }
        } else {
            $breedCategory = str_starts_with($category, 'breed-');
            $classGroup = $breedCategory && in_array($tier, ['working', 'champion', 'club_champion', 'progeny'], true)
                ? 'regulations.classes' : 'classes';
            $label = __('events.'.$classGroup.'.'.$tier, [], $locale);
            $label .= ' · '.($breedCategory
                ? ($breed?->localizedName($locale) ?? $category)
                : __('events.sizes.'.$category, [], $locale));
            if (in_array($sex, ['male', 'female'], true)) {
                $label .= ' · '.__('events.sexes.'.$sex, [], $locale);
            }
        }
        $heat = self::heat($division);

        return $heat > 1 ? $label.' · '.__('events.heat', ['number' => $heat], $locale) : $label;
    }

    public static function base(string $division): string
    {
        return preg_replace('/:heat-[1-9][0-9]*$/', '', $division) ?? $division;
    }

    public static function heat(string $division): int
    {
        if (preg_match('/:heat-([1-9][0-9]*)$/', $division, $matches) === 1) {
            return (int) $matches[1];
        }

        return 1;
    }
}

<?php

namespace App\Modules\Pets\DTO;

use App\Models\Dog;

final readonly class GameEventDivisionData
{
    public static function label(string $division, string $locale, ?Dog $breed = null): string
    {
        [$tier, $category] = array_pad(explode(':', self::base($division), 2), 2, 'all');
        $label = __('events.classes.'.$tier, [], $locale);
        $label .= ' · '.(str_starts_with($category, 'breed-')
            ? ($breed?->localizedName($locale) ?? $category)
            : __('events.sizes.'.$category, [], $locale));
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

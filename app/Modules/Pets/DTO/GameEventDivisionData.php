<?php

namespace App\Modules\Pets\DTO;

use App\Models\Dog;

final readonly class GameEventDivisionData
{
    public static function label(string $division, string $locale, ?Dog $breed = null): string
    {
        [$tier, $category] = array_pad(explode(':', $division, 2), 2, 'all');
        $label = __('events.classes.'.$tier, [], $locale);
        if (str_starts_with($category, 'breed-')) {
            return $label.' · '.($breed?->localizedName($locale) ?? $category);
        }

        return $label.' · '.__('events.sizes.'.$category, [], $locale);
    }
}

<?php

namespace App\Modules\Inventory\Queries;

use App\Models\ItemCategory;

final class CatalogueLabels
{
    /** @return array{id: int, code: string, name: string} */
    public static function category(ItemCategory $category, string $locale): array
    {
        return [
            'id' => $category->id,
            'code' => $category->code,
            'name' => self::text($category->name, $locale, $category->code),
        ];
    }

    /** @param array<string, string|null>|null $translations */
    public static function text(?array $translations, string $locale, string $fallback): string
    {
        return $translations[$locale] ?? $translations['en'] ?? $fallback;
    }
}

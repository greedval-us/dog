<?php

namespace App\Modules\Inventory\Queries;

use App\Models\InventoryItem;
use App\Models\Item;
use App\Modules\Inventory\Calculators\CompetitionAmmunitionRules;
use App\Modules\Pets\Calculators\ItemEffectRules;

/**
 * @phpstan-import-type Risk from ItemEffectRules
 * @phpstan-import-type Rule from ItemEffectRules
 * @phpstan-import-type Ammunition from CompetitionAmmunitionRules
 *
 * @phpstan-type ItemPresentation array{name: string, category: string, categoryCode: string, quality: int, usageLimit: int, characteristics: array<string, mixed>, risks: list<Risk>, bonuses: array<string, int>, grantedEffects: list<array<string, mixed>>, competition: Ammunition|null, ...}
 * @phpstan-type ItemSnapshot array{name: array<string, string>, quality: int, usage_limit: int, characteristics: array<string, mixed>, bonuses: array<string, int>, effect_rules: list<Rule>}
 */
final class CatalogueItemPresentation
{
    public function __construct(private ItemEffectRules $effects, private CompetitionAmmunitionRules $ammunition) {}

    /** @return ItemPresentation&array{description: string, ...} */
    public function forTemplate(Item $item, string $locale): array
    {
        return [
            ...$this->present($item->inventorySnapshot(), $item, $locale),
            'description' => CatalogueLabels::text($item->description, $locale, ''),
        ];
    }

    /** @return ItemPresentation */
    public function forInstance(InventoryItem $instance, string $locale): array
    {
        return $this->present([
            'name' => $instance->name,
            'quality' => $instance->quality,
            'usage_limit' => $instance->usage_limit,
            'characteristics' => $instance->characteristics,
            'bonuses' => $instance->bonuses ?? [],
            'effect_rules' => $instance->effect_rules ?? [],
        ], $instance->item, $locale);
    }

    /**
     * @param  ItemSnapshot  $snapshot
     * @return ItemPresentation
     */
    private function present(array $snapshot, Item $catalogueItem, string $locale): array
    {
        $category = $catalogueItem->category;
        $outcomes = $this->effects->forItem($snapshot['effect_rules'], $snapshot['quality'], $snapshot['name']);

        return [
            'name' => CatalogueLabels::text($snapshot['name'], $locale, $catalogueItem->code),
            'category' => CatalogueLabels::text($category->name, $locale, $category->code),
            'categoryCode' => $category->code,
            'quality' => $snapshot['quality'],
            'usageLimit' => $snapshot['usage_limit'],
            'characteristics' => $snapshot['characteristics'],
            'risks' => $this->effects->uncertain($outcomes),
            'bonuses' => $snapshot['bonuses'],
            'grantedEffects' => $this->effects->guaranteed($outcomes),
            'competition' => $this->ammunition->metadata($snapshot['characteristics']),
        ];
    }
}

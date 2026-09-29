<?php

namespace App\Modules\Pets\Queries;

use App\Models\InventoryItem;
use App\Models\User;
use App\Modules\Pets\Calculators\ItemEffectRules;
use App\Modules\Pets\Calculators\PetCareRules;

final class GetCareItems
{
    public function __construct(private PetCareRules $rules, private ItemEffectRules $riskRules) {}

    /** @return array{items: list<array<string, mixed>>, nextCursor: string|null} */
    public function handle(User $user, string $locale, string $category, int $uses = 1, ?string $cursor = null): array
    {
        $page = $user->inventoryItems()
            ->select(['id', 'user_id', 'item_id', 'name', 'quality', 'bonuses', 'effect_rules', 'remaining_uses'])
            ->with(['item:id,item_category_id,code', 'item.category:id,code'])
            ->where('remaining_uses', '>=', $uses)
            ->whereHas('item.category', fn ($query) => $query->where('code', $category))
            ->oldest('id')->cursorPaginate(12, cursor: $cursor);

        return [
            'items' => array_values($page->getCollection()->map(function (InventoryItem $item) use ($locale): array {
                $outcomes = $this->riskRules->forItem($item->effect_rules ?? [], $item->quality, $item->name);

                return [
                    'id' => $item->id,
                    'category' => $item->item->category->code,
                    'name' => $item->name[$locale] ?? $item->name['en'] ?? $item->item->code,
                    'quality' => $item->quality,
                    'bonus' => $this->rules->qualityBonus($item->item->category->code, $item->quality),
                    'bonuses' => $item->bonuses ?? [],
                    'grantedEffects' => $this->riskRules->guaranteed($outcomes),
                    'risks' => $this->riskRules->uncertain($outcomes),
                    'remainingUses' => $item->remaining_uses,
                ];
            })->all()),
            'nextCursor' => $page->nextCursor()?->encode(),
        ];
    }
}

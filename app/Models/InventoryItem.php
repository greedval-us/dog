<?php

namespace App\Models;

use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $item_id
 * @property int|null $item_purchase_id
 * @property array<string, string> $name
 * @property array<string, int>|null $bonuses
 * @property list<Rule>|null $effect_rules
 * @property int $quality
 * @property int $usage_limit
 * @property int $remaining_uses
 * @property array<string, int|float|string|bool> $characteristics
 *
 * @phpstan-import-type Rule from \App\Modules\Pets\Calculators\ItemEffectRules
 */
#[Fillable(['user_id', 'item_id', 'item_purchase_id', 'name', 'quality', 'usage_limit', 'remaining_uses', 'characteristics', 'bonuses', 'effect_rules'])]
class InventoryItem extends Model
{
    /** @use HasFactory<InventoryItemFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** @return BelongsTo<ItemPurchase, $this> */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(ItemPurchase::class, 'item_purchase_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'item_id' => 'integer',
            'item_purchase_id' => 'integer',
            'name' => 'array',
            'quality' => 'integer',
            'usage_limit' => 'integer',
            'remaining_uses' => 'integer',
            'characteristics' => 'array',
            'bonuses' => 'array',
            'effect_rules' => 'array',
        ];
    }
}

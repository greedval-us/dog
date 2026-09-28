<?php

namespace App\Models;

use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $item_category_id
 * @property string $code
 * @property array<string, string> $name
 * @property array<string, string>|null $description
 * @property array<string, int>|null $bonuses
 * @property list<string>|null $granted_effects
 * @property int $quality
 * @property int $usage_limit
 * @property array<string, int|float|string|bool> $characteristics
 * @property bool $is_active
 */
#[Fillable(['item_category_id', 'code', 'name', 'description', 'quality', 'usage_limit', 'characteristics', 'bonuses', 'granted_effects', 'is_active'])]
class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory;

    /** @return BelongsTo<ItemCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'item_category_id');
    }

    /** @return HasMany<ShopOffer, $this> */
    public function offers(): HasMany
    {
        return $this->hasMany(ShopOffer::class);
    }

    /** @return HasMany<InventoryItem, $this> */
    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    /** @return array{name: array<string, string>, quality: int, usage_limit: int, bonuses: array<string, int>, granted_effects: list<string>, characteristics: array<string, int|float|string|bool>} */
    public function inventorySnapshot(): array
    {
        return [
            'name' => $this->name,
            'quality' => $this->quality,
            'usage_limit' => $this->usage_limit,
            'characteristics' => $this->characteristics,
            'bonuses' => $this->bonuses ?? [],
            'granted_effects' => $this->granted_effects ?? [],
        ];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'item_category_id' => 'integer',
            'name' => 'array',
            'description' => 'array',
            'quality' => 'integer',
            'usage_limit' => 'integer',
            'characteristics' => 'array',
            'bonuses' => 'array',
            'granted_effects' => 'array',
            'is_active' => 'boolean',
        ];
    }
}

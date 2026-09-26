<?php

namespace App\Models;

use Database\Factories\ItemCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property array<string, string> $name
 * @property bool $is_active
 * @property int $sort_order
 */
#[Fillable(['code', 'name', 'is_active', 'sort_order'])]
class ItemCategory extends Model
{
    /** @use HasFactory<ItemCategoryFactory> */
    use HasFactory;

    /** @return HasMany<Item, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'name' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}

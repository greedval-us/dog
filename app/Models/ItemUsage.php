<?php

namespace App\Models;

use Database\Factories\ItemUsageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int $item_id
 * @property int $inventory_item_id
 * @property string $token
 * @property int $uses_spent
 * @property int $uses_before
 * @property int $uses_after
 */
#[Fillable(['user_id', 'item_id', 'inventory_item_id', 'token', 'uses_spent', 'uses_before', 'uses_after'])]
class ItemUsage extends Model
{
    /** @use HasFactory<ItemUsageFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

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

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'item_id' => 'integer',
            'inventory_item_id' => 'integer',
            'uses_spent' => 'integer',
            'uses_before' => 'integer',
            'uses_after' => 'integer',
        ];
    }
}

<?php

namespace App\Models;

use App\Modules\Appearance\Enums\AssetCurrency;
use Database\Factories\AssetUnlockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $game_asset_id
 * @property AssetCurrency $currency
 * @property int $price_paid
 */
#[Fillable(['user_id', 'game_asset_id', 'currency', 'price_paid'])]
class AssetUnlock extends Model
{
    /** @use HasFactory<AssetUnlockFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<GameAsset, $this> */
    public function gameAsset(): BelongsTo
    {
        return $this->belongsTo(GameAsset::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['currency' => AssetCurrency::class, 'price_paid' => 'integer'];
    }
}

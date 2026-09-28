<?php

namespace App\Models;

use App\Modules\Pets\Calculators\ItemEffectRules;
use Database\Factories\ItemEffectRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property bool $is_active
 * @property float $chance_percent
 * @property array<int, int|float>|null $chance_by_quality
 * @property int|null $duration_seconds
 * @property array<int, int>|null $duration_by_quality
 *
 * @phpstan-import-type Rule from ItemEffectRules
 */
#[Fillable(['item_id', 'status_effect_id', 'chance_percent', 'chance_by_quality', 'duration_seconds', 'duration_by_quality', 'is_active'])]
class ItemEffectRule extends Model
{
    /** @use HasFactory<ItemEffectRuleFactory> */
    use HasFactory;

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** @return BelongsTo<StatusEffect, $this> */
    public function statusEffect(): BelongsTo
    {
        return $this->belongsTo(StatusEffect::class);
    }

    /** @return Rule */
    public function snapshot(): array
    {
        return [
            'effect' => $this->statusEffect->snapshot(),
            'chance_percent' => $this->chance_percent,
            'chance_by_quality' => $this->chance_by_quality ?? [],
            'duration_seconds' => $this->duration_seconds,
            'duration_by_quality' => $this->duration_by_quality ?? [],
        ];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['chance_percent' => 'float', 'chance_by_quality' => 'array', 'duration_seconds' => 'integer',
            'duration_by_quality' => 'array', 'is_active' => 'boolean'];
    }
}

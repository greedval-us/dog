<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PetCareActionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @phpstan-import-type Effect from \App\Modules\Pets\Calculators\PetStatusRules
 * @phpstan-import-type Risk from \App\Modules\Pets\Calculators\ItemEffectRules
 *
 * @property list<Effect>|null $granted_effects
 * @property list<Risk>|null $incidents
 * @property array<string, int>|null $status_recovery
 * @property int $id
 * @property int $user_id
 * @property int $pet_id
 * @property string $token
 * @property string $activity_token
 * @property string $group
 * @property string $variant
 * @property array<string, int> $inventory_item_ids
 * @property array<string, int|float> $effects
 * @property array<string, int>|null $stat_gains
 * @property array<string, string>|null $training_name
 * @property CarbonImmutable $ends_at
 * @property CarbonImmutable $available_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property int|null $experience_awarded
 */
#[Fillable(['user_id', 'pet_id', 'token', 'activity_token', 'group', 'variant', 'inventory_item_ids', 'effects', 'granted_effects', 'incidents', 'status_recovery', 'stat_gains', 'training_name', 'ends_at', 'available_at', 'completed_at'])]
class PetCareAction extends Model
{
    /** @use HasFactory<PetCareActionFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'pet_id' => 'integer',
            'inventory_item_ids' => 'array',
            'effects' => 'array',
            'stat_gains' => 'array',
            'training_name' => 'array',
            'granted_effects' => 'array',
            'incidents' => 'array',
            'status_recovery' => 'array',
            'ends_at' => 'immutable_datetime',
            'available_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'experience_awarded' => 'integer',
        ];
    }
}

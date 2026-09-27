<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PetCareActionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property int $pet_id
 * @property string $token
 * @property string $activity_token
 * @property string $group
 * @property string $variant
 * @property array<string, int> $inventory_item_ids
 * @property array<string, int|float> $effects
 * @property CarbonImmutable $ends_at
 * @property CarbonImmutable $available_at
 * @property CarbonImmutable|null $completed_at
 */
#[Fillable(['user_id', 'pet_id', 'token', 'activity_token', 'group', 'variant', 'inventory_item_ids', 'effects', 'ends_at', 'available_at', 'completed_at'])]
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
            'ends_at' => 'immutable_datetime',
            'available_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}

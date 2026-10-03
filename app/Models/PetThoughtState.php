<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $pet_history_event_id
 * @property int|null $pet_history_phrase_id
 * @property CarbonImmutable $last_occurred_at
 */
#[Fillable(['pet_id', 'pet_history_event_id', 'pet_history_phrase_id', 'last_occurred_at'])]
class PetThoughtState extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['last_occurred_at' => 'immutable_datetime'];
    }
}

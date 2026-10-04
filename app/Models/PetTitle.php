<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PetTitleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $pet_id
 * @property int $game_event_entry_id
 * @property string $discipline
 * @property string $frequency
 * @property string $code
 * @property CarbonImmutable $awarded_at
 */
#[Fillable(['pet_id', 'game_event_entry_id', 'discipline', 'frequency', 'code', 'awarded_at'])]
class PetTitle extends Model
{
    /** @use HasFactory<PetTitleFactory> */
    use HasFactory;

    /** @return BelongsTo<Pet, $this> */
    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    /** @return BelongsTo<GameEventEntry, $this> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(GameEventEntry::class, 'game_event_entry_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['awarded_at' => 'immutable_datetime'];
    }
}

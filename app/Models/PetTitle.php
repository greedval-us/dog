<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PetTitleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $pet_id
 * @property int $game_event_entry_id
 * @property string $discipline
 * @property string $frequency
 * @property string $code
 * @property string $kind
 * @property string $scope_key
 * @property array<string,mixed> $details
 * @property string|null $regulation_version
 * @property int|null $game_event_id
 * @property int|null $breed_id
 * @property string|null $class_key
 * @property string|null $sex
 * @property int|null $rank
 * @property int|null $age_months
 * @property CarbonImmutable $awarded_at
 */
#[Fillable(['pet_id', 'game_event_entry_id', 'game_event_id', 'discipline', 'frequency', 'code', 'kind', 'scope_key', 'details', 'regulation_version', 'awarded_at', 'breed_id', 'class_key', 'sex', 'rank', 'age_months'])]
class PetTitle extends Model
{
    /** @use HasFactory<PetTitleFactory> */
    use HasFactory;

    protected $attributes = ['kind' => 'legacy', 'scope_key' => '', 'details' => '[]'];

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

    /** @return HasMany<PetTitleConsumption, $this> */
    public function consumptions(): HasMany
    {
        return $this->hasMany(PetTitleConsumption::class, 'credential_title_id');
    }

    /** @return HasMany<PetTitleConsumption, $this> */
    public function proofs(): HasMany
    {
        return $this->hasMany(PetTitleConsumption::class, 'championship_title_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['awarded_at' => 'immutable_datetime', 'game_event_id' => 'integer', 'breed_id' => 'integer', 'rank' => 'integer', 'age_months' => 'integer', 'scope_key' => 'string', 'details' => 'array'];
    }
}

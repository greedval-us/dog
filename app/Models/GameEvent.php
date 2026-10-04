<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\GameEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $discipline
 * @property string $frequency
 * @property string $status
 * @property CarbonImmutable $registration_opens_at
 * @property CarbonImmutable $closes_at
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property CarbonImmutable|null $settled_at
 * @property string $seed
 * @property array<string, mixed> $rules
 */
#[Fillable(['discipline', 'frequency', 'status', 'registration_opens_at', 'closes_at', 'starts_at', 'ends_at', 'settled_at', 'seed', 'rules'])]
class GameEvent extends Model
{
    /** @use HasFactory<GameEventFactory> */
    use HasFactory;

    /** @return HasMany<GameEventEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(GameEventEntry::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['rules' => 'array', 'registration_opens_at' => 'immutable_datetime', 'closes_at' => 'immutable_datetime', 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'settled_at' => 'immutable_datetime'];
    }
}

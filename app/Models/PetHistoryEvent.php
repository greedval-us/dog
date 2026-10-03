<?php

namespace App\Models;

use Database\Factories\PetHistoryEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $kind
 * @property array<string, string> $name
 * @property list<array{field: string, operator: string, value: int|float|string|bool}>|null $conditions
 * @property int $cooldown_minutes
 * @property int $priority
 * @property bool $is_active
 */
#[Fillable(['code', 'kind', 'name', 'conditions', 'cooldown_minutes', 'priority', 'is_active'])]
class PetHistoryEvent extends Model
{
    /** @use HasFactory<PetHistoryEventFactory> */
    use HasFactory;

    /** @return HasMany<PetHistoryPhrase, $this> */
    public function phrases(): HasMany
    {
        return $this->hasMany(PetHistoryPhrase::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['name' => 'array', 'conditions' => 'array', 'cooldown_minutes' => 'integer', 'priority' => 'integer', 'is_active' => 'boolean'];
    }
}

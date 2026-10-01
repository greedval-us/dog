<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\DogWorkBoardFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property CarbonImmutable $work_date
 * @property CarbonImmutable|null $generated_at
 */
#[Fillable(['work_date', 'generated_at'])]
class DogWorkBoard extends Model
{
    /** @use HasFactory<DogWorkBoardFactory> */
    use HasFactory;

    /** @return HasMany<DogWorkOffer, $this> */
    public function offers(): HasMany
    {
        return $this->hasMany(DogWorkOffer::class);
    }

    /** @return Attribute<never, string|DateTimeInterface> */
    protected function workDate(): Attribute
    {
        return Attribute::make(set: fn (string|DateTimeInterface $value): string => CarbonImmutable::parse($value)->toDateString());
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['work_date' => 'immutable_date', 'generated_at' => 'datetime'];
    }
}

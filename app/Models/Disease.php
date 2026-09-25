<?php

namespace App\Models;

use Database\Factories\DiseaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property array<string, string> $name
 * @property array<string, string>|null $description
 */
#[Fillable(['code', 'name', 'description'])]
class Disease extends Model
{
    /** @use HasFactory<DiseaseFactory> */
    use HasFactory;

    /** @return HasMany<PetDisease, $this> */
    public function episodes(): HasMany
    {
        return $this->hasMany(PetDisease::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['name' => 'array', 'description' => 'array'];
    }
}

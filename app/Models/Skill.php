<?php

namespace App\Models;

use Database\Factories\SkillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Level requirements are percentages of each dog's individual genetic potential.
 *
 * @property int $id
 * @property string $code
 * @property array<string, string> $name
 * @property array<string, string>|null $description
 * @property list<array{price: int, requirements: array<string, int>}>|null $levels
 * @property bool $is_active
 * @property PetSkill $pivot
 */
#[Fillable(['code', 'name', 'description', 'levels', 'is_active'])]
class Skill extends Model
{
    /** @use HasFactory<SkillFactory> */
    use HasFactory;

    /** @return BelongsToMany<Pet, $this, PetSkill> */
    public function pets(): BelongsToMany
    {
        return $this->belongsToMany(Pet::class)->using(PetSkill::class)
            ->withPivot(['level', 'experience', 'last_trained_at', 'cooldown_until'])->withTimestamps();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['name' => 'array', 'description' => 'array', 'levels' => 'array', 'is_active' => 'boolean'];
    }
}

<?php

namespace App\Models;

use Database\Factories\SkillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $code
 * @property array<string, string> $name
 * @property array<string, string>|null $description
 */
#[Fillable(['code', 'name', 'description'])]
class Skill extends Model
{
    /** @use HasFactory<SkillFactory> */
    use HasFactory;

    /** @return BelongsToMany<Pet, $this> */
    public function pets(): BelongsToMany
    {
        return $this->belongsToMany(Pet::class)->withPivot(['level', 'experience'])->withTimestamps();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['name' => 'array', 'description' => 'array'];
    }
}

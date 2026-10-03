<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PetSkillLessonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $experience_awarded
 * @property int $pet_id
 * @property int $skill_id
 * @property int $level
 * @property int $price_paid
 * @property string $token
 * @property CarbonImmutable $trained_at
 * @property CarbonImmutable $cooldown_until
 */
#[Fillable(['user_id', 'pet_id', 'skill_id', 'level', 'price_paid', 'requirements', 'token', 'trained_at', 'cooldown_until', 'currency_transaction_id'])]
class PetSkillLesson extends Model
{
    /** @use HasFactory<PetSkillLessonFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['user_id' => 'integer', 'experience_awarded' => 'integer', 'pet_id' => 'integer', 'skill_id' => 'integer', 'level' => 'integer', 'price_paid' => 'integer',
            'requirements' => 'array', 'trained_at' => 'datetime', 'cooldown_until' => 'datetime'];
    }
}

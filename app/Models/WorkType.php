<?php

namespace App\Models;

use Database\Factories\WorkTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property array<string, string> $name
 * @property array<string, string> $description
 * @property int $coins_reward
 * @property int $gems_bonus
 * @property bool $is_active
 */
#[Fillable(['code', 'name', 'description', 'coins_reward', 'gems_bonus', 'is_active'])]
class WorkType extends Model
{
    /** @use HasFactory<WorkTypeFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['name' => 'array', 'description' => 'array', 'coins_reward' => 'integer', 'gems_bonus' => 'integer', 'is_active' => 'boolean'];
    }
}

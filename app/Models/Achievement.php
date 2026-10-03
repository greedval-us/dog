<?php

namespace App\Models;

use Database\Factories\AchievementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property array<string, string> $name
 * @property array<string, string> $description
 * @property string $image_path
 * @property array{metric: string, target: int} $rules
 * @property array<string, string> $rule_description
 * @property bool $is_active
 * @property int $sort_order
 */
#[Fillable(['code', 'name', 'description', 'image_path', 'rules', 'rule_description', 'is_active', 'sort_order'])]
class Achievement extends Model
{
    /** @use HasFactory<AchievementFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'rules' => 'array',
            'rule_description' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}

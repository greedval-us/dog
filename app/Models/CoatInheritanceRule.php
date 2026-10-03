<?php

namespace App\Models;

use Database\Factories\CoatInheritanceRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $dog_id
 * @property string $first_color
 * @property string $second_color
 * @property string $offspring_color
 * @property int $weight
 * @property Dog $dog
 */
#[Fillable(['dog_id', 'first_color', 'second_color', 'offspring_color', 'weight'])]
class CoatInheritanceRule extends Model
{
    /** @use HasFactory<CoatInheritanceRuleFactory> */
    use HasFactory;

    /** @return BelongsTo<Dog, $this> */
    public function dog(): BelongsTo
    {
        return $this->belongsTo(Dog::class, 'dog_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'dog_id' => 'integer',
            'weight' => 'integer',
        ];
    }
}

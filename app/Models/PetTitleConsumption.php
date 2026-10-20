<?php

namespace App\Models;

use Database\Factories\PetTitleConsumptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $championship_title_id
 * @property int $credential_title_id
 * @property string $purpose
 */
#[Fillable(['championship_title_id', 'credential_title_id', 'purpose'])]
class PetTitleConsumption extends Model
{
    /** @use HasFactory<PetTitleConsumptionFactory> */
    use HasFactory;

    /** @return BelongsTo<PetTitle, $this> */
    public function championship(): BelongsTo
    {
        return $this->belongsTo(PetTitle::class, 'championship_title_id');
    }

    /** @return BelongsTo<PetTitle, $this> */
    public function credential(): BelongsTo
    {
        return $this->belongsTo(PetTitle::class, 'credential_title_id');
    }

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['championship_title_id' => 'integer', 'credential_title_id' => 'integer'];
    }
}

<?php

namespace App\Models;

use Database\Factories\PetHistoryPhraseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $pet_history_event_id
 * @property array<string, string> $text
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['pet_history_event_id', 'text', 'sort_order', 'is_active'])]
class PetHistoryPhrase extends Model
{
    /** @use HasFactory<PetHistoryPhraseFactory> */
    use HasFactory;

    /** @return BelongsTo<PetHistoryEvent, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(PetHistoryEvent::class, 'pet_history_event_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['text' => 'array', 'sort_order' => 'integer', 'is_active' => 'boolean'];
    }
}

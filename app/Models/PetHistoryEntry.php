<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PetHistoryEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $pet_id
 * @property string $kind
 * @property string $event_code
 * @property array<string, string> $title
 * @property array<string, string>|null $message
 * @property array<string, mixed>|null $details
 * @property CarbonImmutable $occurred_at
 */
#[Fillable(['pet_id', 'pet_history_event_id', 'pet_history_phrase_id', 'kind', 'event_code', 'source_key', 'title', 'message', 'details', 'occurred_at'])]
class PetHistoryEntry extends Model
{
    /** @use HasFactory<PetHistoryEntryFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['title' => 'array', 'message' => 'array', 'details' => 'array', 'occurred_at' => 'immutable_datetime'];
    }
}

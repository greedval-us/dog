<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\GameEventEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $game_event_id
 * @property int|null $user_id
 * @property int|null $pet_id
 * @property bool $is_npc
 * @property string $operation_token
 * @property string $registration_hash
 * @property string $division
 * @property string $status
 * @property int $fee
 * @property array<string, mixed> $plan
 * @property list<int> $gear_ids
 * @property array<string, mixed>|null $snapshot
 * @property array<string, mixed>|null $result
 * @property int|null $rank
 * @property int $prize
 * @property CarbonImmutable|null $refunded_at
 * @property CarbonImmutable|null $completed_at
 * @property int|null $experience_awarded
 * @property Pet|null $pet
 * @property User|null $user
 */
#[Fillable(['game_event_id', 'user_id', 'pet_id', 'is_npc', 'operation_token', 'registration_hash', 'division', 'status', 'fee', 'plan', 'gear_ids', 'snapshot', 'result', 'rank', 'prize', 'refunded_at', 'completed_at', 'experience_awarded'])]
class GameEventEntry extends Model
{
    /** @use HasFactory<GameEventEntryFactory> */
    use HasFactory;

    /** @return BelongsTo<GameEvent, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(GameEvent::class, 'game_event_id');
    }

    /** @return BelongsTo<Pet, $this> */
    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['game_event_id' => 'integer', 'user_id' => 'integer', 'pet_id' => 'integer', 'is_npc' => 'boolean', 'fee' => 'integer', 'plan' => 'array', 'gear_ids' => 'array', 'snapshot' => 'array', 'result' => 'array', 'rank' => 'integer', 'prize' => 'integer', 'refunded_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime', 'experience_awarded' => 'integer'];
    }
}

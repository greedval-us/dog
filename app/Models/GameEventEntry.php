<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\GameEventEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /** @return HasMany<PetTitle, $this> */
    public function titles(): HasMany
    {
        return $this->hasMany(PetTitle::class, 'game_event_entry_id');
    }

    /** @param Builder<GameEventEntry> $query
     * @param  list<int>  $petIds
     */
    #[Scope]
    protected function involvingPets(Builder $query, array $petIds): void
    {
        if ($petIds === []) {
            $query->whereIn('pet_id', []);

            return;
        }
        $query->where(function (Builder $entries) use ($petIds): void {
            $entries->whereIn('pet_id', $petIds)->orWhere(function (Builder $groups) use ($petIds): void {
                $groups->whereHas('event', fn (Builder $events) => $events->whereIn('discipline', ['progeny', 'couple', 'breeders'])
                    ->where('rules->version', 3)->whereIn('rules->regulation->format', ['progeny_ring', 'couple_ring', 'breeders_ring']))
                    ->where(function (Builder $members) use ($petIds): void {
                        foreach ($petIds as $petId) {
                            $members->orWhereJsonContains('plan->offspring_ids', $petId);
                            $members->orWhereJsonContains('plan->member_ids', $petId);
                        }
                    });
            });
        });
    }

    /** @param Builder<GameEventEntry> $query */
    #[Scope]
    protected function physical(Builder $query): void
    {
        $query->whereHas('event', fn (Builder $events) => $events->where(fn (Builder $kinds) => $kinds->where('discipline', '!=', 'progeny')
            ->orWhere(fn (Builder $groups) => $groups->where('rules->version', 3)->whereIn('rules->regulation->format', ['progeny_ring', 'couple_ring', 'breeders_ring']))));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['game_event_id' => 'integer', 'user_id' => 'integer', 'pet_id' => 'integer', 'is_npc' => 'boolean', 'fee' => 'integer', 'plan' => 'array', 'gear_ids' => 'array', 'snapshot' => 'array', 'result' => 'array', 'rank' => 'integer', 'prize' => 'integer', 'refunded_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime', 'experience_awarded' => 'integer'];
    }
}

<?php

namespace App\Modules\Pets\Actions;

use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\DTO\EventRegulationData;
use App\Modules\Pets\Exceptions\GameEventUnavailable;
use App\Modules\Pets\Services\GameEventAdmission;
use App\Modules\Players\Enums\PlayerStatus;
use Illuminate\Support\Facades\DB;

final class UpdateGameEventEntry
{
    public function __construct(private GameEventAdmission $admission) {}

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<array-key, mixed>  $gearIds
     */
    public function handle(User $user, int $entryId, array $plan, array $gearIds): GameEventEntry
    {
        $candidate = GameEventEntry::query()->where('user_id', $user->id)->findOrFail($entryId);

        return DB::transaction(function () use ($user, $candidate, $plan, $gearIds): GameEventEntry {
            $event = GameEvent::query()->lockForUpdate()->findOrFail($candidate->game_event_id);
            if (EventRegulationData::isGroupRing($event)) {
                $candidate->refresh();
            }
            $owner = $this->admission->lockRegistrationParticipants($user, $event, $candidate->pet_id, $plan, $candidate->plan);
            $pet = Pet::query()->where('user_id', $owner->id)->lockForUpdate()->findOrFail($candidate->pet_id);
            $entry = GameEventEntry::query()->lockForUpdate()->findOrFail($candidate->id);
            if ($owner->status !== PlayerStatus::Active || $entry->status !== 'registered' || $event->status !== 'registration' || now()->greaterThanOrEqualTo($event->closes_at)) {
                throw new GameEventUnavailable('events.errors.closed');
            }
            $preparation = $this->admission->prepare($owner, $event, $pet, $plan, $gearIds);
            $changes = ['plan' => $preparation->plan, 'gear_ids' => $gearIds];
            if ($event->discipline === 'conformation' || EventRegulationData::isRegulated($event)) {
                $changes['division'] = $this->admission->division($event, $pet, $preparation->plan);
            }
            $entry->update($changes);

            return $entry;
        }, attempts: 3);
    }
}

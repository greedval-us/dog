<?php

namespace App\Modules\Pets\Services;

use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\PetSportRecord;
use App\Models\PetTitle;
use App\Modules\Pets\Calculators\EventChampionshipRules;
use App\Modules\Pets\Calculators\RegionalSportChampionships;
use App\Modules\Pets\Calculators\SpecialExhibitionChampionships;
use App\Modules\Pets\DTO\EventRegulationData;
use App\Modules\Pets\Queries\GetPetEventProfile;

final class RegulatedEventProgress
{
    public function __construct(
        private EventChampionshipRules $rules,
        private GetPetEventProfile $profiles,
        private SpecialExhibitionChampionships $specialShows,
        private RegionalSportChampionships $regionalSports,
        private EventTitleRecorder $titles,
    ) {}

    /** The event processor owns the transaction and holds the pet lock. */
    public function record(GameEvent $event, GameEventEntry $entry, Pet $pet): void
    {
        $won = $entry->rank === 1 && ($entry->result['qualified'] ?? false);
        $record = PetSportRecord::query()->firstOrCreate(['pet_id' => $pet->id, 'discipline' => $event->discipline]);
        $record->starts++;
        $record->wins += (int) $won;
        $balance = config('game-events.career');
        $earned = ($entry->result['eliminated'] ?? false) ? $balance['eliminated_experience'] : ($won ? $balance['win_experience'] : $balance['finish_experience']);
        $record->experience = min($balance['experience_cap'], $record->experience + $earned);
        $record->save();
        $pet->unsetRelation('sportRecords');
        $pet->unsetRelation('titles');
        $profile = $this->profiles->forPet($pet);
        $policy = (int) (EventRegulationData::rules($event)['version'] ?? 1);
        $credentials = $this->credentials($pet, $event, $policy);
        $record->regulatory_progress = $this->rules->progression($event->discipline, $profile, $credentials);
        $record->save();
        $disciplines = $event->discipline === 'conformation' ? ['conformation', 'agility'] : [$event->discipline];
        foreach ($disciplines as $discipline) {
            do {
                $credentials = $this->credentials($pet, $event, $policy);
                $profile['championships'] = $pet->titles()->where('kind', 'championship')->where('discipline', $discipline)->pluck('code')->all();
                $awards = $this->championships($discipline, $profile, $credentials, $event->ends_at->getTimestamp(), $policy);
                $created = false;
                foreach ($awards as $award) {
                    $title = $this->titles->record($event, $entry, $pet, $award, $discipline);
                    $created = $created || $title->wasRecentlyCreated;
                }
            } while ($policy >= 2 && $created);
        }
        $pet->unsetRelation('sportRecords');
        $pet->unsetRelation('titles');
    }

    /** @return list<array<string,mixed>> */
    private function credentials(Pet $pet, GameEvent $event, int $policy): array
    {
        return array_values($pet->titles()->whereIn('regulation_version', array_map(strval(...), range(1, $policy)))
            ->where('awarded_at', '<=', $event->ends_at)->whereIn('kind', ['certificate', 'qualification', 'comparison', 'championship'])
            ->with('consumptions')->orderBy('awarded_at')->orderBy('id')->get()
            ->map(static fn (PetTitle $title): array => [
                'title_id' => $title->id, 'entry_id' => $title->game_event_entry_id,
                'code' => $title->code, 'kind' => $title->kind, 'scope_key' => $title->scope_key,
                'regulation_version' => $title->regulation_version, 'discipline' => $title->discipline,
                'awarded_at' => $title->awarded_at->getTimestamp(),
                'judgeId' => $title->details['judgeId'] ?? '', 'country' => $title->details['country'] ?? '',
                'region' => $title->details['region'] ?? '', 'rank' => $title->details['rank'] ?? '',
                'details' => [...$title->details, 'used_for' => $title->consumptions->pluck('purpose')->all()],
            ])->all());
    }

    /**
     * @param  array<string,mixed>  $profile
     * @param  list<array<string,mixed>>  $credentials
     * @return list<array<string,mixed>>
     */
    private function championships(string $discipline, array $profile, array $credentials, int $at, int $policy): array
    {
        if ($policy < 2) {
            return $this->rules->calculate($discipline, $profile, $credentials, $at);
        }
        if ($discipline === 'conformation') {
            return $this->specialShows->calculate($profile, $credentials, $at);
        }

        return [...$this->regionalSports->calculate($discipline, $profile, $credentials, $at),
            ...array_values(array_filter($this->rules->calculate($discipline, $profile, $credentials, $at), static fn (array $award): bool => $award['code'] === 'CIAG'))];
    }
}

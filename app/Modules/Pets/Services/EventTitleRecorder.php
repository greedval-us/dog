<?php

namespace App\Modules\Pets\Services;

use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\PetTitle;
use App\Models\PetTitleConsumption;
use App\Modules\Pets\DTO\EventRegulationData;
use LogicException;

final class EventTitleRecorder
{
    /**
     * The caller owns the event transaction and holds the pet lock.
     *
     * @param  array<string,mixed>  $award
     */
    public function record(GameEvent $event, GameEventEntry $entry, Pet $pet, array $award, ?string $discipline = null): PetTitle
    {
        $discipline ??= $event->discipline;
        $regulation = EventRegulationData::rules($event);
        $kind = $award['kind'];
        $scope = $award['scope_key'] ?? '';
        $identity = $kind === 'championship' && ! in_array($award['code'], ['SPORT_CH_RUS', 'SPORT_CUP_RUS', 'SPORT_CH_RKF', 'SPORT_BREED_RKF'], true)
            ? ['pet_id' => $pet->id, 'discipline' => $discipline, 'code' => $award['code'], 'kind' => $kind, 'scope_key' => $scope]
            : ['game_event_entry_id' => $entry->id, 'code' => $award['code']];
        $title = PetTitle::query()->firstOrCreate($identity, [
            'pet_id' => $pet->id, 'game_event_entry_id' => $entry->id, 'game_event_id' => $event->id,
            'discipline' => $discipline, 'frequency' => $event->frequency, 'kind' => $kind, 'scope_key' => $scope,
            'details' => [
                ...$regulation,
                'classKey' => $entry->plan['exhibition_class'] ?? $entry->plan['sport_class'] ?? null,
                'program' => $entry->plan['program'] ?? null, 'odor' => $entry->plan['test_type'] ?? null,
                'heightCategory' => $entry->snapshot['profile']['height_category'] ?? null,
                'race_category' => $entry->snapshot['profile']['race_category'] ?? null,
                ...($award['details'] ?? []),
            ],
            'regulation_version' => (string) ($regulation['version'] ?? 1),
            'breed_id' => $pet->dog_id, 'sex' => $entry->snapshot['sex'] ?? $pet->sex->value,
            'age_months' => $entry->snapshot['age_months'] ?? null, 'rank' => $entry->rank,
            'class_key' => $discipline === $event->discipline ? ($entry->plan['exhibition_class'] ?? $entry->plan['sport_class'] ?? null) : null,
            'awarded_at' => $event->ends_at,
        ]);
        $proof = $title->details['credential_title_ids'] ?? [];
        $purpose = $title->details['proof_purpose'] ?? null;
        if ($kind === 'championship' && is_array($proof) && $proof !== [] && is_string($purpose) && $purpose !== '') {
            $credentials = PetTitle::query()->where('pet_id', $pet->id)->whereIn('id', $proof)->where('awarded_at', '<=', $event->ends_at)->get();
            if ($credentials->count() !== count(array_unique($proof)) || $credentials->contains('id', $title->id)) {
                throw new LogicException('Championship proof must refer to the dog’s existing documents.');
            }
            foreach ($credentials as $credential) {
                $usage = PetTitleConsumption::query()->firstOrCreate(['credential_title_id' => $credential->id, 'purpose' => $purpose], ['championship_title_id' => $title->id]);
                if ($usage->championship_title_id !== $title->id) {
                    throw new LogicException('A document cannot be exchanged twice for the same purpose.');
                }
            }
        }

        return $title;
    }
}

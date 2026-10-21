<?php

namespace App\Modules\Pets\Queries;

use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\Calculators\ProgenyRingRules;
use App\Modules\Pets\DTO\EventRegulationData;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

final class GetProgenyRing
{
    public function __construct(private ProgenyRingRules $rules, private PetDecayCalculator $decay) {}

    public function source(GameEvent $event): ?GameEvent
    {
        $id = $event->rules['linked_show_id'] ?? null;
        if (! is_int($id) || $id < 1) {
            return null;
        }
        $source = GameEvent::query()->find($id);

        return $source !== null && $source->discipline === 'conformation' && EventRegulationData::isRegulated($source)
            && $source->closes_at->equalTo($event->closes_at) && $source->ends_at->lessThanOrEqualTo($event->starts_at) ? $source : null;
    }

    /** @param list<int> $ids
     * @return Collection<int,GameEventEntry>
     */
    public function entries(GameEvent $event, array $ids): Collection
    {
        return GameEventEntry::query()->where('game_event_id', $event->rules['linked_show_id'] ?? 0)->whereIn('pet_id', $ids)
            ->where('is_npc', false)->with('pet.activeDiseaseEpisodes')->orderBy('id')->get()->keyBy('pet_id');
    }

    public function reservedHere(GameEvent $event, Pet $pet): bool
    {
        return $pet->activity_token !== null && GameEventEntry::query()->where('game_event_id', $event->id)
            ->where('status', 'frozen')->where('operation_token', $pet->activity_token)->exists();
    }

    /** @return array<int,array<string,mixed>> */
    public function frozenMembers(GameEvent $event): array
    {
        $members = [];
        foreach ($event->entries()->where('status', 'frozen')->orderBy('id')->get() as $entry) {
            foreach ($entry->snapshot['members'] ?? [] as $member) {
                $members[$member['id']] ??= $member;
            }
        }

        return $members;
    }

    /** @return array{eligible:bool,reason:?string,grade:?string,pending:bool,showEntryId:?int} */
    public function member(GameEvent $event, ?GameEventEntry $entry, bool $final = false, ?CarbonImmutable $at = null): array
    {
        $reason = $this->rules->memberReason([
            'status' => $entry?->status, 'class' => $entry?->plan['exhibition_class'] ?? null, 'grade' => $entry?->result['grade'] ?? null,
        ], EventRegulationData::rules($event)['system'] ?? 'rkf', $final);
        if ($entry?->pet !== null) {
            $pet = $entry->pet;
            $reservedHere = $this->reservedHere($event, $pet);
            $pet->advanceTo($at ?? CarbonImmutable::now(), $this->decay);
            $states = $pet->statePercentages(null);
            if (! $pet->isActive() || $pet->retired_at !== null) {
                $reason = 'events.errors.archived';
            } elseif ($states['health'] < 60 || $pet->activeDiseaseEpisodes->isNotEmpty()) {
                $reason = 'events.errors.health';
            } elseif (! $reservedHere && ($states['energy'] < 35 || $pet->energy < ($event->rules['energy_cost'] ?? 0))) {
                $reason = 'events.errors.energy';
            } elseif (! $reservedHere && $pet->isBusy() && ($pet->activity_ends_at === null || $pet->activity_ends_at->isAfter($event->starts_at))) {
                $reason = 'events.errors.busy';
            }
        }

        return ['eligible' => $reason === null, 'reason' => $reason, 'grade' => $entry?->result['grade'] ?? null,
            'pending' => $reason === null && $entry?->status !== 'completed', 'showEntryId' => $entry?->id];
    }
}

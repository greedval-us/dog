<?php

namespace App\Modules\Pets\Queries;

use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Modules\Pets\Calculators\ShowGroupRules;
use App\Modules\Pets\DTO\EventRegulationData;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/** @phpstan-import-type Origin from GetPetBreedingOrigin */
final class GetShowGroup
{
    public function __construct(private GetProgenyRing $progeny, private GetPetBreedingOrigin $origins, private ShowGroupRules $rules) {}

    public function source(GameEvent $event): ?GameEvent
    {
        return $this->progeny->source($event);
    }

    public function freezeAt(GameEvent $event): CarbonImmutable
    {
        $source = $this->source($event);
        if ($source === null) {
            return $event->closes_at;
        }
        if (EventRegulationData::isProgenyRing($event)) {
            return $source->ends_at;
        }

        $saved = $event->rules['group_freezes_at'] ?? null;

        return is_string($saved) ? CarbonImmutable::parse($saved)->max($source->ends_at)->min($event->starts_at) : $source->ends_at;
    }

    /** @param list<int> $ids
     * @return Collection<int,GameEventEntry>
     */
    public function entries(GameEvent $event, array $ids): Collection
    {
        return $this->progeny->entries($event, $ids);
    }

    public function reservedHere(GameEvent $event, Pet $pet): bool
    {
        return $this->progeny->reservedHere($event, $pet);
    }

    /** @return array<int,array<string,mixed>> */
    public function frozenMembers(GameEvent $event): array
    {
        return $this->progeny->frozenMembers($event);
    }

    /** @return array{eligible:bool,reason:?string,grade:?string,pending:bool,showEntryId:?int} */
    public function member(GameEvent $event, ?GameEventEntry $entry, bool $final = false, ?CarbonImmutable $at = null): array
    {
        $data = $this->progeny->member($event, $entry, $final, $at);
        if (! EventRegulationData::isProgenyRing($event) && $data['reason'] !== null) {
            $data['reason'] = str_replace('events.errors.progeny_', 'events.errors.group_', $data['reason']);
        }

        return $data;
    }

    /** @param list<Pet> $members */
    public function compositionReason(GameEvent $event, array $members): ?string
    {
        $origins = $event->discipline === 'breeders' ? $this->origins->forPets(array_map(static fn (Pet $pet): int => $pet->id, $members)) : [];

        return $this->rules->compositionReason($event->discipline, array_map(fn (Pet $pet): array => $this->identity($pet, $origins[$pet->id] ?? null), $members));
    }

    /** @param array<int,Origin> $origins */
    public function candidateReason(GameEvent $event, Pet $anchor, Pet $candidate, array $origins): ?string
    {
        return $this->rules->candidateReason($event->discipline, $this->identity($anchor, $origins[$anchor->id] ?? null), $this->identity($candidate, $origins[$candidate->id] ?? null));
    }

    /** @param Origin|null $origin
     * @return array{id:int,breed_id:int,sex:string,owner_id:?int,breeder_key:?string,kennel_key:?string}
     */
    private function identity(Pet $pet, ?array $origin): array
    {
        return ['id' => $pet->id, 'breed_id' => $pet->dog_id, 'sex' => $pet->sex->value, 'owner_id' => $pet->user_id,
            'breeder_key' => $origin['breeder_key'] ?? null, 'kennel_key' => $origin['kennel_key'] ?? null];
    }
}

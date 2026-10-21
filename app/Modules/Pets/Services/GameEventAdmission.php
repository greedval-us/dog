<?php

namespace App\Modules\Pets\Services;

use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\InventoryItem;
use App\Models\Pet;
use App\Models\PetSportRecord;
use App\Models\PetTitle;
use App\Models\User;
use App\Modules\Inventory\Calculators\CompetitionAmmunitionRules;
use App\Modules\Pets\Calculators\GameEventAdmissionRules;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\Calculators\RegulatedEventRules;
use App\Modules\Pets\Calculators\SkillRules;
use App\Modules\Pets\DTO\EventRegulationData;
use App\Modules\Pets\DTO\GameEventAdmissionData;
use App\Modules\Pets\DTO\GameEventProtocol;
use App\Modules\Pets\DTO\PetCompetitionSnapshot;
use App\Modules\Pets\Enums\ExhibitionClass;
use App\Modules\Pets\Enums\GameEventDiscipline;
use App\Modules\Pets\Exceptions\GameEventUnavailable;
use App\Modules\Pets\Queries\GetGameEventParticipation;
use App\Modules\Pets\Queries\GetPetEventProfile;
use App\Modules\Pets\Queries\GetShowGroup;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * @phpstan-import-type Plan from GameEventProtocol
 * @phpstan-import-type Gear from GameEventProtocol
 * @phpstan-import-type Snapshot from GameEventProtocol
 */
final class GameEventAdmission
{
    public function __construct(private PetDecayCalculator $decay, private SkillRules $skills, private CompetitionAmmunitionRules $ammunition, private GameEventAdmissionRules $admissionRules, private RegulatedEventRules $regulatedRules, private GetPetEventProfile $profiles, private GetShowGroup $groups, private GetGameEventParticipation $participation) {}

    /**
     * The caller holds the event lock; owners and pets are locked in ascending order.
     *
     * @param  array<string,mixed>  $plan
     * @param  array<string,mixed>  $previousPlan
     */
    public function lockRegistrationParticipants(User $user, GameEvent $event, int $anchorId, array $plan, array $previousPlan = []): User
    {
        if (! EventRegulationData::isGroupRing($event)) {
            return User::query()->lockForUpdate()->findOrFail($user->id);
        }
        $ids = [$anchorId];
        $key = EventRegulationData::isProgenyRing($event) ? 'offspring_ids' : 'member_ids';
        foreach ([$plan, $previousPlan] as $selection) {
            $members = $selection[$key] ?? [];
            if (is_array($members) && array_is_list($members) && count($members) <= 5
                && count(array_filter($members, static fn (mixed $id): bool => is_int($id) && $id > 0)) === count($members)) {
                $ids = [...$ids, ...$members];
            }
        }
        $ids = array_values(array_unique($ids));
        $initial = Pet::query()->whereIn('id', $ids)->get(['id', 'user_id'])->keyBy('id');
        $ownerIds = $initial->pluck('user_id')->filter()->push($user->id)->unique();
        $owners = User::query()->whereIn('id', $ownerIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $pets = Pet::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get(['id', 'user_id']);
        foreach ($pets as $member) {
            if ($member->user_id !== $initial->get($member->id)?->user_id || ($member->user_id !== null && ! $owners->has($member->user_id))) {
                throw new GameEventUnavailable('events.errors.unavailable');
            }
        }

        return $owners->get($user->id) ?? throw new GameEventUnavailable('events.errors.unavailable');
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<array-key, mixed>  $gearIds
     *
     * @throws GameEventUnavailable
     */
    public function prepare(User $user, GameEvent $event, Pet $pet, array $plan, array $gearIds, bool $lockGear = false): GameEventAdmissionData
    {
        return new GameEventAdmissionData(
            $this->plan($event, $pet, $plan),
            $this->gear($user, $event, $pet, $gearIds, $lockGear),
        );
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return Plan
     */
    public function plan(GameEvent $event, Pet $pet, array $plan): array
    {
        $stages = $plan['stages'] ?? [];
        $regulated = EventRegulationData::isRegulated($event);
        $stageCount = $regulated ? count($event->rules['stages'] ?? []) : 3;
        if (! is_array($stages) || ! array_is_list($stages) || $stageCount < 1 || $stageCount > 6 || count($stages) !== $stageCount) {
            throw new GameEventUnavailable('events.errors.plan');
        }
        $decisions = [];
        foreach ($stages as $stage) {
            if (! in_array($stage, ['careful', 'balanced', 'bold'], true)) {
                throw new GameEventUnavailable('events.errors.plan');
            }
            $decisions[] = $stage;
        }
        $result = ['stages' => $decisions];
        if ($regulated) {
            $regulation = EventRegulationData::rules($event);
            if (! $this->regulatedRules->validPlan($event->discipline, $regulation, $this->profiles->forPet($pet), $pet->ageMonthsAt($event->starts_at), $plan)) {
                throw new GameEventUnavailable('events.errors.qualification');
            }
            foreach (['exhibition_class', 'sport_class', 'program', 'test_type', 'handler_category'] as $key) {
                if (isset($plan[$key]) && is_string($plan[$key])) {
                    $result[$key] = $plan[$key];
                }
            }
        } elseif ($event->discipline === GameEventDiscipline::Conformation->value) {
            $available = ExhibitionClass::availableAt($pet->ageMonthsAt($event->starts_at));
            $class = $plan['exhibition_class'] ?? $available[0]->value ?? null;
            if (! is_string($class) || ! in_array(ExhibitionClass::tryFrom($class), $available, true)) {
                throw new GameEventUnavailable('events.errors.exhibition_class');
            }
            $result['exhibition_class'] = $class;
        } elseif (array_key_exists('exhibition_class', $plan)) {
            throw new GameEventUnavailable('events.errors.exhibition_class');
        }
        if (EventRegulationData::isGroupRing($event) && ! EventRegulationData::isProgenyRing($event)) {
            $ids = $plan['member_ids'] ?? [];
            if ($decisions !== array_fill(0, $stageCount, 'balanced')) {
                throw new GameEventUnavailable('events.errors.plan');
            }
            sort($ids);
            $result['member_ids'] = $ids;
            $this->ringMembers($event, $pet, $ids);
        }
        if (GameEventDiscipline::from($event->discipline)->isDocumentary()) {
            $ids = $plan['offspring_ids'] ?? [];
            if (! is_array($ids) || ! array_is_list($ids) || count($ids) < 3 || count($ids) > 5
                || count(array_unique($ids)) !== count($ids) || count(array_filter($ids, fn ($id): bool => is_int($id) && $id > 0)) !== count($ids)) {
                throw new GameEventUnavailable('events.errors.offspring');
            }
            $children = Pet::query()->whereIn('id', $ids)->where('dog_id', $pet->dog_id)
                ->where(fn ($query) => $query->where('father_id', $pet->id)->orWhere('mother_id', $pet->id))->count();
            if ($children !== count($ids)) {
                throw new GameEventUnavailable('events.errors.offspring');
            }
            sort($ids);
            $result['offspring_ids'] = $ids;
            if (EventRegulationData::isGroupRing($event)) {
                if ($decisions !== array_fill(0, $stageCount, 'balanced')) {
                    throw new GameEventUnavailable('events.errors.plan');
                }
                $this->ringMembers($event, $pet, $ids);
            }
        }

        return $result;
    }

    public function reason(GameEvent $event, Pet $pet, CarbonImmutable $at): ?string
    {
        $pet->advanceTo($at, $this->decay);
        $states = $pet->statePercentages(null);
        if (EventRegulationData::isGroupRing($event) && $this->groups->reservedHere($event, $pet)) {
            return ! $pet->isActive() || $pet->retired_at !== null ? 'events.errors.archived'
                : ($states['health'] < 60 || $pet->activeDiseaseEpisodes()->exists() ? 'events.errors.health' : null);
        }

        return $this->admissionRules->blockingReasons(EventRegulationData::isGroupRing($event) ? GameEventDiscipline::Conformation : GameEventDiscipline::from($event->discipline), [
            'active' => $pet->isActive(), 'retired' => $pet->retired_at !== null, 'ageMonths' => $pet->ageMonthsAt($event->starts_at),
            'health' => $states['health'], 'hasDisease' => $pet->activeDiseaseEpisodes()->exists(),
            'energyPercentage' => $states['energy'], 'energy' => $pet->energy,
            'busy' => $pet->isBusy(), 'activityEndsAt' => $pet->activity_ends_at,
        ], [
            'startsAt' => $event->starts_at, 'closesAt' => EventRegulationData::isGroupRing($event) ? $event->starts_at : $event->closes_at, 'energyCost' => $event->rules['energy_cost'] ?? 0,
            ...($this->minimumAge($event)),
        ])[0] ?? null;
    }

    /**
     * @param  array<array-key, mixed>  $ids
     * @return list<Gear>
     */
    public function gear(User $user, GameEvent $event, Pet $pet, array $ids, bool $lock = false): array
    {
        if ((GameEventDiscipline::from($event->discipline)->isDocumentary() || EventRegulationData::isGroupRing($event)) && $ids !== []) {
            throw new GameEventUnavailable('events.errors.gear');
        }
        if (! array_is_list($ids) || count($ids) > 4 || count(array_unique($ids)) !== count($ids)
            || count(array_filter($ids, fn ($id): bool => is_int($id) && $id > 0)) !== count($ids)) {
            throw new GameEventUnavailable('events.errors.gear');
        }
        $query = InventoryItem::query()->where('user_id', $user->id)->whereIn('id', $ids)->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }
        $instances = $query->get();
        if ($instances->count() !== count($ids)) {
            throw new GameEventUnavailable('events.errors.gear');
        }
        $slots = [];
        $gear = [];
        foreach ($instances as $item) {
            $spec = $this->ammunition->metadata($item->characteristics);
            if ($spec === null || isset($slots[$spec['slot']]) || ! in_array($event->discipline, $spec['disciplines'], true)
                || ! in_array($pet->size->value, $spec['sizes'], true) || $item->remaining_uses < 1
                || ($event->discipline === 'canicross' && in_array($spec['slot'], ['body', 'line', 'handler'], true) && $spec['phase'] !== 'performance')) {
                throw new GameEventUnavailable('events.errors.gear');
            }
            if (EventRegulationData::isRegulated($event) && $event->discipline === 'agility' && $spec['phase'] === 'performance') {
                throw new GameEventUnavailable('events.errors.agility_gear');
            }
            $slots[$spec['slot']] = true;
            $gear[] = ['id' => $item->id, 'name' => $item->name, 'slot' => $spec['slot'], 'phase' => $spec['phase'], 'modifiers' => $spec['modifiers']];
        }
        if ($event->discipline === 'canicross' && array_diff(['body', 'line', 'handler'], array_keys($slots)) !== []) {
            throw new GameEventUnavailable('events.errors.kit');
        }

        return $gear;
    }

    /** @param Plan|null $plan */
    public function division(GameEvent $event, Pet $pet, ?array $plan = null): string
    {
        if (EventRegulationData::isRegulated($event)) {
            return $this->regulatedRules->division($event->discipline, EventRegulationData::rules($event), $this->profiles->forPet($pet), $plan ?? []);
        }
        if ($event->discipline === GameEventDiscipline::Conformation->value) {
            $class = $plan['exhibition_class'] ?? ExhibitionClass::availableAt($pet->ageMonthsAt($event->starts_at))[0]->value ?? null;
            if ($class === null) {
                throw new GameEventUnavailable('events.errors.exhibition_class');
            }

            return $class.':breed-'.$pet->dog_id.':'.$pet->sex->value;
        }
        $tier = PetSportRecord::query()->where('pet_id', $pet->id)->where('discipline', $event->discipline)->value('tier') ?? 0;
        $class = ['novice', 'open', 'champion'][min(2, (int) $tier)];
        $group = GameEventDiscipline::from($event->discipline)->divisionGroup($pet->size->value, $pet->dog_id);

        return $class.':'.$group;
    }

    /**
     * @param  Plan  $plan
     * @param  list<Gear>  $gear
     * @return Snapshot
     */
    public function snapshot(GameEvent $event, Pet $pet, array $plan, array $gear): array
    {
        $pet->loadMissing(['dog', 'skills', 'sportRecords']);
        $modifiers = array_fill_keys(['precision', 'stamina', 'pace', 'focus'], 0.0);
        foreach ($gear as $item) {
            foreach ($item['modifiers'] as $key => $value) {
                $modifiers[$key] += $value;
            }
        }
        foreach ($modifiers as $key => $value) {
            $modifiers[$key] = max(-0.2, min(0.2, $value));
        }
        $offspring = [];
        $members = [];
        if (EventRegulationData::isDocumentary($event)) {
            $children = Pet::query()->whereIn('id', $plan['offspring_ids'] ?? throw new GameEventUnavailable('events.errors.offspring'))->orderBy('id')
                ->with(['titles' => fn ($query) => $query->reorder()->orderBy('id')->select(['pet_id', 'discipline', 'frequency', 'code'])])->get();
            foreach ($children as $child) {
                $offspring[] = [
                    'id' => $child->id, 'name' => $child->name, 'exterior' => $child->exterior ?? [],
                    'titles' => array_values($child->titles->map(fn (PetTitle $title): array => $title->only(['discipline', 'frequency', 'code']))->all()),
                ];
            }
        }
        if (EventRegulationData::isGroupRing($event)) {
            $source = $this->groups->source($event) ?? throw new GameEventUnavailable('events.errors.progeny_entry');
            $frozenMembers = $this->groups->frozenMembers($event);
            foreach ($this->ringMembers($event, $pet, $plan[EventRegulationData::isProgenyRing($event) ? 'offspring_ids' : 'member_ids'] ?? []) as $individual) {
                $member = $individual->pet ?? throw new GameEventUnavailable('events.errors.progeny_entry');
                $member->loadMissing(['dog', 'skills', 'sportRecords']);
                $member->advanceTo($this->groups->freezeAt($event), $this->decay);
                $members[] = $frozenMembers[$member->id] ?? [...PetCompetitionSnapshot::fromPet($member, $this->skills, 'conformation'),
                    'id' => $member->id, 'exhibition_class' => $individual->plan['exhibition_class'], 'grade' => $individual->result['grade'],
                    'gear' => $individual->snapshot['gear'] ?? [], 'modifiers' => $individual->snapshot['modifiers'] ?? [],
                    'age_months' => $member->ageMonthsAt($event->starts_at), 'show_entry_id' => $individual->id];
                if (EventRegulationData::isProgenyRing($event) && $member->id !== $pet->id) {
                    $offspring[] = ['id' => $member->id, 'name' => $member->name, 'exterior' => $member->exterior ?? [], 'titles' => []];
                }
            }
        }

        return [
            ...PetCompetitionSnapshot::fromPet($pet, $this->skills, $event->discipline),
            'age_months' => $pet->ageMonthsAt($event->starts_at), 'sex' => $pet->sex->value,
            'exhibition_class' => $plan['exhibition_class'] ?? null,
            'gear' => $gear, 'modifiers' => $modifiers, 'offspring' => $offspring,
            ...($members !== [] ? ['members' => $members] : []),
            ...(EventRegulationData::isRegulated($event) ? [
                'profile' => $this->profiles->forPet($pet), 'regulation' => EventRegulationData::rules($event),
                ...($event->discipline === 'nosework' ? ['nosework_time_limit' => $this->regulatedRules->noseworkTimeLimit(EventRegulationData::rules($event), $plan)] : []),
                ...($event->discipline === 'conformation' ? ['show_presentation_multipliers' => $this->regulatedRules->showPresentationMultipliers()] : []),
            ] : []),
        ];
    }

    /** @return array{minimumMonths?:int} */
    private function minimumAge(GameEvent $event): array
    {
        if (EventRegulationData::isGroupRing($event)) {
            return ['minimumMonths' => 0];
        }
        if (! EventRegulationData::isRegulated($event)) {
            return [];
        }

        return ['minimumMonths' => $this->regulatedRules->minimumMonths($event->discipline, EventRegulationData::rules($event), [])];
    }

    /** @param list<int> $ids
     * @return Collection<int,GameEventEntry>
     */
    private function ringMembers(GameEvent $event, Pet $parent, array $ids): Collection
    {
        $error = EventRegulationData::isProgenyRing($event) ? 'events.errors.progeny_entry' : 'events.errors.group_entry';
        $source = $this->groups->source($event);
        if ($source === null || $source->status === 'cancelled') {
            throw new GameEventUnavailable($error);
        }
        $entries = $this->groups->entries($event, [$parent->id, ...$ids]);
        foreach ([$parent->id, ...$ids] as $id) {
            $individual = $entries->get($id);
            if ($individual === null || $individual->pet === null) {
                throw new GameEventUnavailable($error);
            }
            if (($reason = $this->groups->member($event, $individual, $source->status === 'settled', $source->status === 'settled' ? $this->groups->freezeAt($event) : null)['reason']) !== null) {
                throw new GameEventUnavailable($reason);
            }
            if (($reason = $this->reason($event, $individual->pet, $source->status === 'settled' ? $this->groups->freezeAt($event) : CarbonImmutable::now())) !== null) {
                throw new GameEventUnavailable($reason);
            }
            if (($reason = $this->participation->petReason($individual->pet, $event)) !== null) {
                throw new GameEventUnavailable($reason);
            }
        }
        if (! EventRegulationData::isProgenyRing($event)) {
            if (($reason = $this->groups->compositionReason($event, array_values($entries->pluck('pet')->all()))) !== null) {
                throw new GameEventUnavailable($reason);
            }
            $identity = [$parent->id, ...$ids];
            sort($identity);
            foreach ($event->entries()->where('pet_id', '!=', $parent->id)->whereNotIn('status', ['cancelled', 'withdrawn'])->get() as $other) {
                $otherIds = EventRegulationData::memberIds($event, $other->plan, $other->pet_id);
                sort($otherIds);
                if ($otherIds === $identity) {
                    throw new GameEventUnavailable('events.errors.group_duplicate');
                }
            }
        }

        return $entries;
    }
}

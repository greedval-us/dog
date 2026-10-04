<?php

namespace App\Modules\Pets\Services;

use App\Models\GameEvent;
use App\Models\InventoryItem;
use App\Models\Pet;
use App\Models\PetSportRecord;
use App\Models\PetTitle;
use App\Models\User;
use App\Modules\Inventory\Calculators\CompetitionAmmunitionRules;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\Calculators\SkillRules;
use App\Modules\Pets\DTO\PetStatSnapshot;
use App\Modules\Pets\Enums\PetStat;
use App\Modules\Pets\Exceptions\GameEventUnavailable;
use Carbon\CarbonImmutable;

final class GameEventAdmission
{
    public function __construct(private PetDecayCalculator $decay, private SkillRules $skills, private CompetitionAmmunitionRules $ammunition) {}

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<array-key, mixed>  $gearIds
     * @return array{plan:array<string, mixed>, gear:list<array<string, mixed>>}
     */
    public function prepare(User $user, GameEvent $event, Pet $pet, array $plan, array $gearIds, bool $lockGear = false): array
    {
        return [
            'plan' => $this->plan($event, $pet, $plan),
            'gear' => $this->gear($user, $event, $pet, $gearIds, $lockGear),
        ];
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    public function plan(GameEvent $event, Pet $pet, array $plan): array
    {
        $stages = $plan['stages'] ?? [];
        if (! is_array($stages) || ! array_is_list($stages) || count($stages) !== 3
            || count(array_filter($stages, fn ($stage): bool => is_string($stage) && in_array($stage, ['careful', 'balanced', 'bold'], true))) !== 3) {
            throw new GameEventUnavailable('events.errors.plan');
        }
        $result = ['stages' => $stages];
        if ($event->discipline === 'progeny') {
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
        }

        return $result;
    }

    public function reason(GameEvent $event, Pet $pet, CarbonImmutable $at): ?string
    {
        if ($event->discipline === 'progeny') {
            return null;
        }
        $pet->advanceTo($at, $this->decay);
        if (! $pet->isActive() || $pet->automaticRetirementAt()->lessThanOrEqualTo($event->ends_at)) {
            return 'events.errors.archived';
        }
        $states = $pet->statePercentages(null);
        if ($states['health'] < 60 || $pet->activeDiseaseEpisodes()->exists()) {
            return 'events.errors.health';
        }
        if ($states['energy'] < 35 || $pet->energy < ($event->rules['energy_cost'] ?? 0)) {
            return 'events.errors.energy';
        }
        if ($pet->isBusy() && ($pet->activity_ends_at === null || $pet->activity_ends_at->greaterThan($event->closes_at))) {
            return 'events.errors.busy';
        }

        return null;
    }

    /**
     * @param  array<array-key, mixed>  $ids
     * @return list<array<string, mixed>>
     */
    public function gear(User $user, GameEvent $event, Pet $pet, array $ids, bool $lock = false): array
    {
        if ($event->discipline === 'progeny' && $ids !== []) {
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
            $slots[$spec['slot']] = true;
            $gear[] = ['id' => $item->id, 'name' => $item->name, 'slot' => $spec['slot'], 'phase' => $spec['phase'], 'modifiers' => $spec['modifiers']];
        }
        if ($event->discipline === 'canicross' && array_diff(['body', 'line', 'handler'], array_keys($slots)) !== []) {
            throw new GameEventUnavailable('events.errors.kit');
        }

        return $gear;
    }

    public function division(GameEvent $event, Pet $pet): string
    {
        $tier = PetSportRecord::query()->where('pet_id', $pet->id)->where('discipline', $event->discipline)->value('tier') ?? 0;
        $class = ['novice', 'open', 'champion'][min(2, (int) $tier)];
        $group = match ($event->discipline) {
            'agility' => $pet->size->value,
            'conformation', 'progeny' => 'breed-'.$pet->dog_id,
            default => 'all',
        };

        return $class.':'.$group;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  list<array<string, mixed>>  $gear
     * @return array<string, mixed>
     */
    public function snapshot(GameEvent $event, Pet $pet, array $plan, array $gear): array
    {
        $pet->loadMissing(['dog', 'skills']);
        $stats = [];
        $potentials = [];
        foreach (PetStat::cases() as $stat) {
            $stats[$stat->value] = (int) $pet->getAttribute($stat->value);
            $potentials[$stat->value] = (int) $pet->getAttribute($stat->potentialColumn());
        }
        $skills = [];
        foreach ($pet->skills as $skill) {
            if ($this->skills->isActive(new PetStatSnapshot($stats, $potentials), $skill->levels, $skill->is_active, $skill->pivot->level, ! $pet->isActive())) {
                $skills[$skill->code] = $skill->pivot->level;
            }
        }
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
        if ($event->discipline === 'progeny') {
            $children = Pet::query()->whereIn('id', $plan['offspring_ids'])->orderBy('id')
                ->with(['titles' => fn ($query) => $query->reorder()->orderBy('id')->select(['pet_id', 'discipline', 'frequency', 'code'])])->get();
            foreach ($children as $child) {
                $offspring[] = [
                    'id' => $child->id, 'name' => $child->name, 'exterior' => $child->getAttribute('exterior') ?? [],
                    'titles' => $child->titles->map(fn (PetTitle $title): array => $title->only(['discipline', 'frequency', 'code']))->all(),
                ];
            }
        }

        return [
            'name' => $pet->name, 'breed' => $pet->dog->breed, 'breed_id' => $pet->dog_id, 'size' => $pet->size->value,
            'stats' => $stats, 'potentials' => $potentials, 'states' => $pet->statePercentages(null),
            'skills' => $skills, 'exterior' => $pet->getAttribute('exterior') ?? [],
            'career_experience' => (int) (PetSportRecord::query()->where('pet_id', $pet->id)->where('discipline', $event->discipline)->value('experience') ?? 0),
            'gear' => $gear, 'modifiers' => $modifiers, 'offspring' => $offspring,
        ];
    }
}

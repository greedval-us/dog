<?php

namespace App\Modules\Pets\Queries;

use App\Models\Dog;
use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\InventoryItem;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Inventory\Calculators\CompetitionAmmunitionRules;
use App\Modules\Pets\Calculators\GameEventPerformance;
use App\Modules\Pets\Calculators\GameEventSimulator;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\Calculators\SkillRules;
use App\Modules\Pets\DTO\GameEventDivisionData;
use App\Modules\Pets\DTO\PetCompetitionSnapshot;
use App\Modules\Pets\DTO\PetTitleData;
use App\Modules\Pets\Enums\GameEventDiscipline;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

final class GetGameEvents
{
    public function __construct(
        private PetDecayCalculator $decay,
        private CompetitionAmmunitionRules $ammunition,
        private SkillRules $skills,
        private GameEventPerformance $performance,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function index(User $user, array $filters = []): array
    {
        $query = GameEvent::query()->where(function (Builder $query) use ($user): void {
            $query->where('ends_at', '>', now())->orWhere(function (Builder $history) use ($user): void {
                $history->where('starts_at', '>=', now()->subDays(7))->whereHas('entries', fn (Builder $entries) => $entries->where('user_id', $user->id));
            });
        })->withCount($this->entryCounts())->with(['entries' => fn (Relation $entries) => $entries->where('user_id', $user->id)]);
        if (isset($filters['frequency'])) {
            $query->where('frequency', $filters['frequency']);
        }
        if (isset($filters['kind'])) {
            $disciplines = GameEventDiscipline::exhibitions();
            $filters['kind'] === 'exhibition' ? $query->whereIn('discipline', $disciplines) : $query->whereNotIn('discipline', $disciplines);
        }
        $page = $query->orderBy('starts_at')->orderBy('id')->cursorPaginate(30);

        return [
            'events' => array_values($page->getCollection()->map(fn (GameEvent $event): array => $this->summary($event, $user))->all()),
            'filters' => ['frequency' => $filters['frequency'] ?? null, 'kind' => $filters['kind'] ?? null],
            'nextCursor' => $page->nextCursor()?->encode(),
        ];
    }

    /** @return array<string, mixed> */
    public function show(User $user, GameEvent $event, string $locale): array
    {
        return [
            ...$this->showState($user, $event, $locale),
            'dogs' => $this->registrationDogs($user, $locale, $event),
            'equipment' => $this->registrationEquipment($user, $locale),
        ];
    }

    /** @return array{event:array<string, mixed>, entry:array<string, mixed>|null} */
    public function showState(User $user, GameEvent $event, string $locale, ?string $division = null): array
    {
        $event->load(['entries' => fn (Relation $entries) => $entries->where('user_id', $user->id)->with(['pet.dog', 'user:id,username'])])->loadCount($this->entryCounts());
        $own = $event->entries->firstWhere('user_id', $user->id);
        $groups = $event->entries()->whereIn('status', ['registered', 'frozen', 'completed'])
            ->select('division')->selectRaw('SUM(CASE WHEN is_npc THEN 0 ELSE 1 END) AS human_count, SUM(CASE WHEN is_npc THEN 1 ELSE 0 END) AS club_count')
            ->groupBy('division')->orderBy('division')->get()->sortBy('division', SORT_NATURAL)->values();
        $divisionKeys = $groups->pluck('division');
        $activeDivision = $division !== null && $divisionKeys->contains($division) ? $division
            : ($own !== null && $divisionKeys->contains($own->division) ? $own->division : $divisionKeys->first());
        $entries = $activeDivision === null ? collect() : $event->entries()->where('division', $activeDivision)
            ->whereIn('status', ['registered', 'frozen', 'completed'])->with(['pet.dog', 'user:id,username'])
            ->orderByRaw('rank IS NULL')->orderBy('rank')->orderBy('id')->get();
        $breedIds = $divisionKeys->map(fn (string $key): ?int => preg_match('/^[^:]+:breed-(\d+)/', $key, $match) === 1 ? (int) $match[1] : null)->filter();
        $breeds = Dog::query()->whereIn('id', $breedIds)->get()->keyBy('id');
        $breedFor = fn (string $key): ?Dog => preg_match('/^[^:]+:breed-(\d+)/', $key, $match) === 1 ? $breeds->get((int) $match[1]) : null;

        return [
            'event' => [
                ...$this->summary($event, $user),
                'entries' => array_values($entries->map(fn (GameEventEntry $entry): array => $this->entry($entry, $user, $locale, $breedFor($entry->division)))->all()),
                'activeDivision' => $activeDivision,
                'divisions' => $groups->map(fn (GameEventEntry $group): array => [
                    'key' => $group->division, 'label' => GameEventDivisionData::label($group->division, $locale, $breedFor($group->division)),
                    'humanCount' => (int) $group->getAttribute('human_count'), 'clubCount' => (int) $group->getAttribute('club_count'),
                ])->all(),
            ],
            'entry' => $own === null ? null : $this->entry($own, $user, $locale, $own->pet->dog ?? $breedFor($own->division)),
        ];
    }

    /** @return array<string, \Closure> */
    private function entryCounts(): array
    {
        return [
            'entries' => fn (Builder $entries) => $entries->whereIn('status', ['registered', 'frozen', 'completed']),
            'entries as human_count' => fn (Builder $entries) => $entries->where('is_npc', false)->whereIn('status', ['registered', 'frozen', 'completed']),
            'entries as club_count' => fn (Builder $entries) => $entries->where('is_npc', true)->whereIn('status', ['frozen', 'completed']),
        ];
    }

    /** @return array<string, mixed> */
    private function summary(GameEvent $event, User $user): array
    {
        $at = now();
        $own = $event->entries->firstWhere('user_id', $user->id);

        return [
            'id' => $event->id,
            'discipline' => $event->discipline,
            'frequency' => $event->frequency,
            'status' => match ($event->status) {
                'registration' => 'scheduled', 'frozen' => 'locked', 'settled' => 'completed', default => 'cancelled'
            },
            'startsAt' => $event->starts_at->toIso8601String(),
            'closesAt' => $event->closes_at->toIso8601String(),
            'opensAt' => $event->registration_opens_at->toIso8601String(),
            'fee' => $event->rules['fee'],
            'prizes' => $event->rules['prizes'],
            'entryCount' => (int) $event->getAttribute('entries_count'),
            'humanCount' => (int) $event->getAttribute('human_count'),
            'clubCount' => (int) $event->getAttribute('club_count'),
            'fieldSize' => (int) ($event->rules['field_size'] ?? 8),
            'calculationVersion' => (int) $event->rules['version'],
            'participationRules' => [
                'playerDailyLimit' => (int) config('game-events.daily_limit', 3),
                'petDailyLimit' => (int) config('game-events.pet_daily_limit', 2),
                'petRestHours' => (int) config('game-events.pet_rest_hours', 2),
            ],
            'canRegister' => $event->status === 'registration' && $at->greaterThanOrEqualTo($event->registration_opens_at) && $at->lessThan($event->closes_at) && $own === null,
            'stages' => array_map(fn (string $key): array => ['key' => $key, 'label' => $key, 'options' => ['careful', 'balanced', 'bold']], $event->rules['stages']),
        ];
    }

    /** @return array<string, mixed> */
    private function entry(GameEventEntry $entry, User $user, string $locale, ?Dog $breed = null): array
    {
        $result = $entry->result;
        if ($result !== null && isset($result['stages'])) {
            $result['stages'] = array_map(fn (array $stage): array => [...$stage, 'reason' => __('events.notes.'.($stage['note'] ?? 'clean'), [], $locale)], $result['stages']);
        } elseif ($result !== null) {
            $result = ['version' => GameEventSimulator::VERSION, 'score' => 0, 'time' => 0, 'penalties' => 0, 'eliminated' => true, 'stages' => [], 'reason' => __($result['reason'] ?? 'events.errors.unavailable', [], $locale)];
        }

        return [
            'id' => $entry->id, 'petId' => $entry->pet_id,
            'name' => $entry->snapshot['name'] ?? $entry->pet->name ?? __('events.club_dog', [], $locale),
            'ownerName' => $entry->is_npc ? null : $entry->user?->username,
            'isNpc' => $entry->is_npc, 'division' => $entry->division, 'status' => $entry->status,
            'divisionLabel' => GameEventDivisionData::label($entry->division, $locale, $breed),
            'plan' => $entry->user_id === $user->id || $entry->status !== 'registered' ? $entry->plan : ['stages' => [], 'offspring_ids' => []],
            'gearIds' => $entry->user_id === $user->id ? $entry->gear_ids : [],
            'rank' => $entry->rank, 'prize' => $entry->prize, 'result' => $result,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function registrationDogs(User $user, string $locale, ?GameEvent $event = null): array
    {
        $pets = $user->pets()->with(['dog', 'titles', 'skills', 'sportRecords'])->withExists('activeDiseaseEpisodes')->orderBy('id')->get();
        $ids = $pets->modelKeys();
        $children = Pet::query()->where(fn (Builder $query) => $query->whereIn('father_id', $ids)->orWhereIn('mother_id', $ids))
            ->withCount('titles')->orderBy('id')->get(['id', 'dog_id', 'father_id', 'mother_id', 'name', 'exterior']);

        return array_values($pets->map(function (Pet $pet) use ($children, $locale, $event): array {
            $pet->advanceTo(now(), $this->decay);

            return [
                'id' => $pet->id, 'name' => $pet->name, 'breed' => $pet->dog->localizedName($locale), 'size' => $pet->size->value,
                'isActive' => $pet->isActive(), 'isBusy' => $pet->isBusy(), 'exterior' => $pet->exterior,
                'titles' => PetTitleData::fromPet($pet, $locale),
                'preparation' => $event === null ? null : $this->preparation($pet, $event, $locale),
                'offspring' => array_values($children->filter(fn (Pet $child): bool => $child->dog_id === $pet->dog_id && ($child->father_id === $pet->id || $child->mother_id === $pet->id))->map(fn (Pet $child): array => [
                    'id' => $child->id, 'name' => $child->name, 'exterior' => $child->exterior, 'titlesCount' => (int) $child->getAttribute('titles_count'),
                ])->all()),
            ];
        })->all());
    }

    /** @return array<string, mixed> */
    private function preparation(Pet $pet, GameEvent $event, string $locale): array
    {
        $snapshot = PetCompetitionSnapshot::fromPet($pet, $this->skills, $event->discipline);
        $preparation = $this->performance->preparation($event->discipline, $snapshot);
        $kind = GameEventDiscipline::from($event->discipline);
        $states = $snapshot['states'];
        $reasons = [];
        if (! $kind->isDocumentary()) {
            if (! $pet->isActive() || $pet->automaticRetirementAt()->lessThanOrEqualTo($event->ends_at)) {
                $reasons[] = __('events.errors.archived', [], $locale);
            }
            if ($states['health'] < 60 || $pet->getAttribute('active_disease_episodes_exists')) {
                $reasons[] = __('events.errors.health', [], $locale);
            }
            if ($states['energy'] < 35 || $pet->energy < ($event->rules['energy_cost'] ?? 0)) {
                $reasons[] = __('events.errors.energy', [], $locale);
            }
            if ($pet->isBusy() && ($pet->activity_ends_at === null || $pet->activity_ends_at->greaterThan($event->closes_at))) {
                $reasons[] = __('events.errors.busy', [], $locale);
            }
        }
        $tier = (int) ($pet->sportRecords->firstWhere('discipline', $event->discipline)->tier ?? 0);
        $division = ['novice', 'open', 'champion'][min(2, $tier)].':'.$kind->divisionGroup($pet->size->value, $pet->dog_id);
        $stageKeys = array_values($event->rules['stages']);

        return [
            ...$preparation,
            'stats' => $snapshot['stats'], 'potentials' => $snapshot['potentials'], 'skills' => $snapshot['skills'],
            'pedigree' => $snapshot['pedigree'], 'divisionLabel' => GameEventDivisionData::label($division, $locale, $pet->dog),
            'blockingReasons' => $reasons,
            'stages' => array_map(fn (int $index, string $key): array => [
                'key' => $key,
                'weights' => $this->performance->stageWeights($event->discipline, $index),
                'quality' => round($this->performance->stageQuality($event->discipline, $snapshot['stats'], $snapshot['skills'], $index) * $preparation['careMultiplier'], 2),
            ], array_keys($stageKeys), $stageKeys),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function registrationEquipment(User $user, string $locale): array
    {
        $items = InventoryItem::query()->where('user_id', $user->id)->where('remaining_uses', '>', 0)
            ->whereNotNull('characteristics->competition')->orderBy('id')->get();
        $equipment = [];
        foreach ($items as $item) {
            $metadata = $this->ammunition->metadata($item->characteristics);
            if ($metadata === null) {
                continue;
            }
            $equipment[] = [
                'id' => $item->id, 'name' => $item->name[$locale] ?? $item->name['en'] ?? '',
                'remainingUses' => $item->remaining_uses, ...$metadata,
            ];
        }

        return $equipment;
    }
}

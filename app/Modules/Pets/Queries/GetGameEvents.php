<?php

namespace App\Modules\Pets\Queries;

use App\Models\Dog;
use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\InventoryItem;
use App\Models\Pet;
use App\Models\PetTitle;
use App\Models\User;
use App\Modules\Inventory\Calculators\CompetitionAmmunitionRules;
use App\Modules\Pets\Calculators\GameEventAdmissionRules;
use App\Modules\Pets\Calculators\GameEventParticipationRules;
use App\Modules\Pets\Calculators\GameEventPerformance;
use App\Modules\Pets\Calculators\GameEventSimulator;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\Calculators\RegulatedEventRules;
use App\Modules\Pets\Calculators\SkillRules;
use App\Modules\Pets\DTO\EventRegulationData;
use App\Modules\Pets\DTO\GameEventDivisionData;
use App\Modules\Pets\DTO\PetCompetitionSnapshot;
use App\Modules\Pets\DTO\PetTitleData;
use App\Modules\Pets\Enums\ExhibitionClass;
use App\Modules\Pets\Enums\GameEventDiscipline;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Lang;

/**
 * @phpstan-import-type Participation from GameEventParticipationRules
 * @phpstan-import-type Origin from GetPetBreedingOrigin
 *
 * @phpstan-type GroupRegistration array{entries:Collection<int,GameEventEntry>,candidates:array<int,Pet>,origins:array<int,Origin>,saved:array<int,list<int>>}
 */
final class GetGameEvents
{
    public function __construct(
        private PetDecayCalculator $decay,
        private CompetitionAmmunitionRules $ammunition,
        private SkillRules $skills,
        private GameEventPerformance $performance,
        private GameEventAdmissionRules $admissionRules,
        private RegulatedEventRules $regulatedRules,
        private GetPetEventProfile $profiles,
        private GetGameEventParticipation $participation,
        private GetProgenyRing $progeny,
        private GetShowGroup $groups,
        private GetPetBreedingOrigin $origins,
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
        })->where(fn (Builder $query) => $query->where('status', '!=', 'cancelled')->orWhereHas('entries', fn (Builder $entries) => $entries->where('user_id', $user->id)))
            ->withCount($this->entryCounts())->with(['entries' => fn (Relation $entries) => $entries->where('user_id', $user->id)]);
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

    /** @return array{event:array<string, mixed>, entry:array<string, mixed>|null, ownEntries:list<array<string, mixed>>} */
    public function showState(User $user, GameEvent $event, string $locale, ?string $division = null, ?int $selectedPetId = null): array
    {
        $event->load(['entries' => fn (Relation $entries) => $entries->where('user_id', $user->id)->with(['pet.dog', 'user:id,username', 'titles'])->orderBy('id')])->loadCount($this->entryCounts());
        $own = $event->entries->firstWhere('user_id', $user->id);
        if ($selectedPetId !== null && $event->entries->count() > 1) {
            $own = $event->entries->firstWhere('pet_id', $selectedPetId);
        }
        $groups = $event->entries()->whereIn('status', ['registered', 'frozen', 'completed'])
            ->select('division')->selectRaw('SUM(CASE WHEN is_npc THEN 0 ELSE 1 END) AS human_count, SUM(CASE WHEN is_npc THEN 1 ELSE 0 END) AS club_count')
            ->groupBy('division')->orderBy('division')->get()->sortBy('division', SORT_NATURAL)->values();
        $divisionKeys = $groups->pluck('division');
        $activeDivision = $division !== null && $divisionKeys->contains($division) ? $division
            : ($own !== null && $divisionKeys->contains($own->division) ? $own->division : $divisionKeys->first());
        $entries = $activeDivision === null ? collect() : $event->entries()->where('division', $activeDivision)
            ->whereIn('status', ['registered', 'frozen', 'completed'])->with(['pet.dog', 'user:id,username', 'titles'])
            ->orderByRaw('rank IS NULL')->orderBy('rank')->orderBy('id')->get();
        $breedIds = $divisionKeys->map(fn (string $key): ?int => preg_match('/^[^:]+:breed-(\d+)/', $key, $match) === 1 ? (int) $match[1] : null)->filter();
        $breeds = Dog::query()->whereIn('id', $breedIds)->get()->keyBy('id');
        $breedFor = fn (string $key): ?Dog => preg_match('/^[^:]+:breed-(\d+)/', $key, $match) === 1 ? $breeds->get((int) $match[1]) : null;

        return [
            'event' => [
                ...$this->summary($event, $user, withLinks: true),
                'entries' => array_values($entries->map(fn (GameEventEntry $entry): array => $this->entry($entry, $user, $locale, $event->discipline, $breedFor($entry->division)))->all()),
                'activeDivision' => $activeDivision,
                'divisions' => $groups->map(fn (GameEventEntry $group): array => [
                    'key' => $group->division, 'label' => GameEventDivisionData::label($group->division, $locale, $breedFor($group->division)),
                    'humanCount' => (int) $group->getAttribute('human_count'), 'clubCount' => (int) $group->getAttribute('club_count'),
                ])->all(),
            ],
            'entry' => $own === null ? null : $this->entry($own, $user, $locale, $event->discipline, $own->pet->dog ?? $breedFor($own->division)),
            'ownEntries' => array_values($event->entries->map(fn (GameEventEntry $entry): array => $this->entry($entry, $user, $locale, $event->discipline, $entry->pet->dog ?? $breedFor($entry->division)))->all()),
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
    private function summary(GameEvent $event, User $user, bool $withLinks = false): array
    {
        $at = now();
        $own = $event->entries->firstWhere('user_id', $user->id);
        $related = null;
        /** @var Collection<int,GameEvent> $contests */
        $contests = new Collection;
        if ($withLinks && EventRegulationData::isGroupRing($event)) {
            $related = $this->groups->source($event);
        } elseif ($withLinks && $event->discipline === 'conformation') {
            $contests = GameEvent::query()->whereIn('discipline', ['progeny', 'couple', 'breeders'])->where('rules->linked_show_id', $event->id)
                ->where('rules->version', 3)->whereIn('rules->regulation->format', ['progeny_ring', 'couple_ring', 'breeders_ring'])
                ->where('status', '!=', 'cancelled')->orderBy('starts_at')->orderBy('id')->get();
            $related = $contests->firstWhere('discipline', 'progeny');
        }

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
            'regulation' => EventRegulationData::fromEvent($event, app()->getLocale()),
            ...($related === null ? [] : [(EventRegulationData::isGroupRing($event) ? 'linkedShow' : 'progenyContest') => [
                'id' => $related->id, 'name' => __('events.disciplines.'.$related->discipline),
                'startsAt' => $related->starts_at->toIso8601String(), 'status' => $related->status,
            ]]),
            ...(! $withLinks || $event->discipline !== 'conformation' ? [] : ['groupContests' => $contests->map(fn (GameEvent $contest): array => [
                'id' => $contest->id, 'name' => __('events.disciplines.'.$contest->discipline), 'discipline' => $contest->discipline,
                'startsAt' => $contest->starts_at->toIso8601String(), 'status' => $contest->status,
            ])->all()]),
            'participationRules' => [
                'playerDailyLimit' => (int) config('game-events.daily_limit', 3),
                'petDailyLimit' => (int) config('game-events.pet_daily_limit', 2),
                'petRestHours' => (int) config('game-events.pet_rest_hours', 2),
            ],
            'canRegister' => $event->status === 'registration' && $at->greaterThanOrEqualTo($event->registration_opens_at) && $at->lessThan($event->closes_at)
                && ($own === null || ($event->discipline === 'conformation' && EventRegulationData::isRegulated($event)) || EventRegulationData::isGroupRing($event)),
            'stages' => array_map(fn (string $key): array => ['key' => $key, 'label' => EventRegulationData::isRegulated($event) ? __('events.stages.'.$key) : $key, 'options' => ['careful', 'balanced', 'bold']], $event->rules['stages']),
        ];
    }

    /** @return array<string, mixed> */
    private function entry(GameEventEntry $entry, User $user, string $locale, string $discipline, ?Dog $breed = null): array
    {
        $result = $entry->result;
        if ($result !== null && isset($result['stages'])) {
            $result['stages'] = array_map(fn (array $stage): array => [...$stage, 'reason' => __('events.notes.'.($stage['note'] ?? 'clean'), [], $locale)], $result['stages']);
            if (isset($result['members'])) {
                $result['members'] = array_map(static fn (array $member): array => [...$member, 'gradeLabel' => __('events.grades.'.$member['grade'], [], $locale)], $result['members']);
            }
            if (($result['version'] ?? null) === 3) {
                $grade = (string) ($result['grade'] ?? (($result['qualified'] ?? false) ? 'qualified' : 'not_qualified'));
                $qualified = (bool) ($result['qualified'] ?? false);
                if ($discipline === 'canicross' && ! ($result['eliminated'] ?? false)) {
                    $raceQualified = $result['race_qualified'] ?? null;
                    if ($raceQualified === null && $entry->titles->contains(static fn (PetTitle $title): bool => $title->code === 'RACE_QUALIFICATION'
                        && $title->kind === 'qualification' && $title->discipline === 'canicross')) {
                        $raceQualified = true;
                    }
                    $grade = $raceQualified === null ? 'finished' : ($raceQualified ? 'qualified' : 'not_qualified');
                    $qualified = $raceQualified === true;
                }
                $result['assessment'] = ['code' => $grade, 'label' => __('events.grades.'.$grade, [], $locale), 'qualified' => $qualified];
                $result['standings'] = array_map(fn (array $standing): array => [
                    ...$standing, 'label' => __('events.standings.'.(string) $standing['key'], [], $locale),
                ], $result['standings'] ?? []);
            }
        } elseif ($result !== null) {
            $result = ['version' => GameEventSimulator::VERSION, 'score' => 0, 'time' => 0, 'penalties' => 0, 'eliminated' => true, 'stages' => [], 'reason' => __($result['reason'] ?? 'events.errors.unavailable', [], $locale)];
        }

        return [
            'id' => $entry->id, 'petId' => $entry->pet_id,
            'name' => $entry->snapshot['name'] ?? $entry->pet->name ?? __('events.club_dog', [], $locale),
            'ownerName' => $entry->is_npc ? null : $entry->user?->username,
            'isNpc' => $entry->is_npc, 'division' => $entry->division, 'status' => $entry->status,
            'divisionLabel' => GameEventDivisionData::label($entry->division, $locale, $breed),
            'plan' => $entry->user_id === $user->id || $entry->status !== 'registered' ? $entry->plan : ['stages' => [], 'offspring_ids' => [], 'member_ids' => []],
            'gearIds' => $entry->user_id === $user->id ? $entry->gear_ids : [],
            'rank' => $entry->rank, 'prize' => $entry->prize, 'result' => $result,
            'awards' => $entry->relationLoaded('titles') ? $entry->titles->map(fn ($title): array => PetTitleData::fromTitle($title, $locale))->values()->all() : [],
            'ageMonths' => $entry->snapshot['age_months'] ?? null,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function registrationDogs(User $user, string $locale, ?GameEvent $event = null): array
    {
        $pets = $user->pets()->with(['dog', 'titles', 'skills', 'sportRecords'])->withExists('activeDiseaseEpisodes')->orderBy('id')->get();
        $ids = array_values($pets->map(fn (Pet $pet): int => $pet->id)->all());
        $participations = $event === null ? [] : $this->participation->entries($user->id, $ids, [$event]);
        $children = Pet::query()->where(fn (Builder $query) => $query->whereIn('father_id', $ids)->orWhereIn('mother_id', $ids))
            ->withCount('titles')->orderBy('id')->get(['id', 'dog_id', 'father_id', 'mother_id', 'name', 'exterior']);
        $ringEntries = $event !== null && EventRegulationData::isProgenyRing($event)
            ? $this->progeny->entries($event, [...$ids, ...$children->modelKeys()]) : null;
        $groupData = $event !== null && EventRegulationData::isGroupRing($event) && ! EventRegulationData::isProgenyRing($event)
            ? $this->groupRegistrationData($user, $event, $ids, array_values($pets->map(static fn (Pet $pet): int => $pet->dog_id)->unique()->all())) : null;
        $eligibility = function (?GameEventEntry $entry) use ($event, $locale): array {
            $data = $this->progeny->member($event, $entry);

            return [...$data, 'reason' => $data['reason'] === null ? null : __($data['reason'], [], $locale),
                'gradeLabel' => $data['grade'] === null ? null : __('events.grades.'.$data['grade'], [], $locale)];
        };

        return array_values($pets->map(function (Pet $pet) use ($children, $locale, $event, $user, $participations, $ringEntries, $eligibility, $groupData): array {
            $pet->advanceTo(now(), $this->decay);
            $ageMonths = $pet->ageMonthsAt($event->starts_at ?? now());
            $classes = $event?->discipline === GameEventDiscipline::Conformation->value ? ExhibitionClass::availableAt($ageMonths) : [];
            $profile = $this->profiles->forPet($pet);
            $entryOptions = $event !== null && EventRegulationData::isRegulated($event)
                ? $this->regulatedRules->entryOptions($event->discipline, EventRegulationData::rules($event), $profile, $ageMonths) : [];
            $eligibleOptions = array_values(array_filter($entryOptions, fn (array $option): bool => $option['eligible']));

            return [
                'id' => $pet->id, 'name' => $pet->name, 'breed' => $pet->dog->localizedName($locale), 'size' => $pet->size->value,
                'isActive' => $pet->isActive(), 'isBusy' => $pet->isBusy(), 'exterior' => $pet->exterior,
                'titles' => PetTitleData::fromPet($pet, $locale),
                'ageMonths' => $ageMonths,
                'availableClasses' => array_map(fn (ExhibitionClass $class): array => ['key' => $class->value, 'label' => __('events.classes.'.$class->value, [], $locale)], $classes),
                'defaultClass' => $classes[0]->value ?? null,
                'entryOptions' => array_map(fn (array $option): array => EventRegulationData::option($option, $locale, $pet->dog), $entryOptions),
                'defaultEntryOption' => $eligibleOptions[0]['key'] ?? null,
                'profile' => ['heightCm' => $profile['height_cm'], 'heightCategory' => $profile['height_category']],
                'preparation' => $event === null ? null : $this->preparation($pet, $event, $locale, $user->id, $participations),
                ...($ringEntries === null ? [] : ['progenyEligibility' => $eligibility($ringEntries->get($pet->id))]),
                ...($groupData === null ? [] : [
                    'kennelName' => $groupData['origins'][$pet->id]['kennel_name'] ?? null,
                    'groupEligibility' => $this->groupEligibility($event, $groupData['entries']->get($pet->id), $locale,
                        $event->discipline === 'breeders' && ! isset($groupData['origins'][$pet->id]) ? 'events.errors.kennel_origin' : null),
                    'groupMembers' => $this->groupCandidates($event, $pet, $groupData, $locale),
                ]),
                'offspring' => array_values($children->filter(fn (Pet $child): bool => $child->dog_id === $pet->dog_id && ($child->father_id === $pet->id || $child->mother_id === $pet->id))->map(fn (Pet $child): array => [
                    'id' => $child->id, 'name' => $child->name, 'exterior' => $child->exterior, 'titlesCount' => (int) $child->getAttribute('titles_count'),
                    ...($ringEntries === null ? [] : $eligibility($ringEntries->get($child->id))),
                ])->all()),
            ];
        })->all());
    }

    /**
     * @param  list<int>  $ids
     * @param  list<int>  $breedIds
     * @return GroupRegistration
     */
    private function groupRegistrationData(User $user, GameEvent $event, array $ids, array $breedIds): array
    {
        $saved = [];
        foreach ($event->entries()->where('user_id', $user->id)->whereIn('pet_id', $ids)->orderBy('id')->get(['pet_id', 'plan']) as $entry) {
            $memberIds = $entry->plan['member_ids'] ?? [];
            if ($entry->pet_id !== null && is_array($memberIds)) {
                $saved[$entry->pet_id] = array_values(array_filter($memberIds, static fn (mixed $id): bool => is_int($id)));
            }
        }
        $savedIds = array_values(array_unique(array_merge(...array_values($saved))));
        $source = $this->groups->source($event);
        $sourceIds = $source?->entries()->where('is_npc', false)
            ->whereHas('pet', fn (Builder $pets) => $pets->whereIn('dog_id', $breedIds))->pluck('pet_id')->all() ?? [];
        /** @var Collection<int,GameEventEntry> $entries */
        $entries = $source === null ? new Collection : $this->groups->entries($event, array_values(array_unique([...$ids, ...$sourceIds, ...$savedIds])));
        $entries->load('pet.user:id,username');
        $candidates = [];
        foreach ($entries as $entry) {
            if ($entry->pet !== null) {
                $candidates[$entry->pet->id] = $entry->pet;
            }
        }
        foreach (Pet::query()->whereIn('id', array_diff($savedIds, array_keys($candidates)))->with('user:id,username')->get() as $pet) {
            $candidates[$pet->id] = $pet;
        }
        ksort($candidates);

        return ['entries' => $entries, 'candidates' => $candidates, 'saved' => $saved,
            'origins' => $event->discipline === 'breeders' ? $this->origins->forPets(array_values(array_unique([...$ids, ...array_keys($candidates)]))) : []];
    }

    /**
     * @return array{eligible:bool,reason:?string,grade:?string,gradeLabel:?string,pending:bool,showEntryId:?int}
     */
    private function groupEligibility(GameEvent $event, ?GameEventEntry $entry, string $locale, ?string $reason = null): array
    {
        $data = $this->groups->member($event, $entry);
        $reason ??= $data['reason'];

        return [...$data, 'eligible' => $reason === null, 'reason' => $reason === null ? null : Lang::string($reason, [], $locale),
            'gradeLabel' => $data['grade'] === null ? null : Lang::string('events.grades.'.$data['grade'], [], $locale)];
    }

    /**
     * @param  GroupRegistration  $data
     * @return list<array<string,mixed>>
     */
    private function groupCandidates(GameEvent $event, Pet $anchor, array $data, string $locale): array
    {
        $savedIds = $data['saved'][$anchor->id] ?? [];
        $members = [];
        foreach ($data['candidates'] as $candidate) {
            $reason = $this->groups->candidateReason($event, $anchor, $candidate, $data['origins']);
            if ($reason !== null && ! in_array($candidate->id, $savedIds, true)) {
                continue;
            }
            $members[] = ['id' => $candidate->id, 'name' => $candidate->name, 'sex' => $candidate->sex->value,
                'ownerName' => $candidate->user?->username, 'kennelName' => $data['origins'][$candidate->id]['kennel_name'] ?? null,
                ...$this->groupEligibility($event, $data['entries']->get($candidate->id), $locale, $reason)];
        }
        foreach (array_diff($savedIds, array_keys($data['candidates'])) as $missingId) {
            $members[] = ['id' => $missingId, 'name' => '#'.$missingId, 'eligible' => false,
                'reason' => __('events.errors.group_entry', [], $locale), 'grade' => null, 'gradeLabel' => null, 'pending' => false, 'showEntryId' => null];
        }

        return $members;
    }

    /** @param list<Participation> $participations
     * @return array<string, mixed>
     */
    private function preparation(Pet $pet, GameEvent $event, string $locale, int $ownerId, array $participations): array
    {
        $performanceDiscipline = EventRegulationData::isGroupRing($event) ? 'conformation' : $event->discipline;
        $snapshot = PetCompetitionSnapshot::fromPet($pet, $this->skills, $performanceDiscipline);
        $preparation = $this->performance->preparation($performanceDiscipline, $snapshot);
        $kind = GameEventDiscipline::from($performanceDiscipline);
        $states = $snapshot['states'];
        $reasons = $this->admissionRules->blockingReasons($kind, [
            'active' => $pet->isActive(), 'retired' => $pet->retired_at !== null, 'ageMonths' => $pet->ageMonthsAt($event->starts_at),
            'health' => $states['health'], 'hasDisease' => (bool) $pet->getAttribute('active_disease_episodes_exists'),
            'energyPercentage' => $states['energy'], 'energy' => $pet->energy,
            'busy' => $pet->isBusy(), 'activityEndsAt' => $pet->activity_ends_at,
        ], [
            'startsAt' => $event->starts_at, 'closesAt' => EventRegulationData::isGroupRing($event) ? $event->starts_at : $event->closes_at, 'energyCost' => $event->rules['energy_cost'] ?? 0,
            ...(EventRegulationData::isRegulated($event) ? [
                'minimumMonths' => EventRegulationData::isGroupRing($event) ? 0 : $this->regulatedRules->minimumMonths($event->discipline, EventRegulationData::rules($event), []),
            ] : []),
        ]);
        $tier = (int) ($pet->sportRecords->firstWhere('discipline', $event->discipline)->tier ?? 0);
        $division = ['novice', 'open', 'champion'][min(2, $tier)].':'.$kind->divisionGroup($pet->size->value, $pet->dog_id);
        if ($kind === GameEventDiscipline::Conformation) {
            $class = ExhibitionClass::availableAt($pet->ageMonthsAt($event->starts_at))[0]->value ?? 'unavailable';
            $division = $class.':breed-'.$pet->dog_id.':'.$pet->sex->value;
        }
        if (EventRegulationData::isGroupRing($event)) {
            $division = $event->discipline.':breed-'.$pet->dog_id;
        } elseif (EventRegulationData::isRegulated($event)) {
            $options = $this->regulatedRules->entryOptions($event->discipline, EventRegulationData::rules($event), $this->profiles->forPet($pet), $pet->ageMonthsAt($event->starts_at));
            $eligible = array_values(array_filter($options, fn (array $option): bool => $option['eligible']));
            if ($eligible !== []) {
                $division = $this->regulatedRules->division($event->discipline, EventRegulationData::rules($event), $this->profiles->forPet($pet), $eligible[0]['plan']);
            } else {
                $reasons[] = 'events.errors.qualification';
            }
        }
        $stageKeys = array_values($event->rules['stages']);
        $participationReason = $this->participation->reason($ownerId, $pet->id, $event, $participations);
        if ($participationReason !== null) {
            $reasons[] = $participationReason;
        }

        return [
            ...$preparation,
            'stats' => $snapshot['stats'], 'potentials' => $snapshot['potentials'], 'skills' => $snapshot['skills'],
            'pedigree' => $snapshot['pedigree'], 'divisionLabel' => GameEventDivisionData::label($division, $locale, $pet->dog),
            'blockingReasons' => array_map(fn (string $reason): string => __($reason, [
                'pet_daily_limit' => (int) config('game-events.pet_daily_limit'), 'pet_rest_hours' => (int) config('game-events.pet_rest_hours'),
            ], $locale), $reasons),
            'stages' => array_map(fn (int $index, string $key): array => [
                'key' => $key,
                'weights' => $this->performance->stageWeights($performanceDiscipline, $event->discipline === 'agility' ? $index % 3 : min(2, $index)),
                'quality' => round($this->performance->stageQuality($performanceDiscipline, $snapshot['stats'], $snapshot['skills'], $event->discipline === 'agility' ? $index % 3 : min(2, $index)) * $preparation['careMultiplier'], 2),
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

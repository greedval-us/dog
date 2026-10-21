<?php

namespace App\Modules\Pets\Queries;

use App\Models\GameEvent;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Calculators\GameEventAdmissionRules;
use App\Modules\Pets\Calculators\GameEventTitleOpportunities;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\Calculators\RegionalSportChampionships;
use App\Modules\Pets\Calculators\RegulatedEventRules;
use App\Modules\Pets\DTO\EventRegulationData;
use App\Modules\Pets\Enums\GameEventDiscipline;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;

final class GetTitleProgressEvents
{
    private const RECOMMENDATIONS_PER_PATH = 3;

    public function __construct(
        private RegulatedEventRules $rules,
        private GameEventTitleOpportunities $opportunities,
        private GameEventAdmissionRules $admission,
        private PetDecayCalculator $decay,
        private RegionalSportChampionships $championships,
        private GetGameEventParticipation $participation,
    ) {}

    /**
     * @param  array<string,mixed>  $profile
     * @return list<array<string,mixed>>
     */
    public function candidates(User $owner, Pet $pet, array $profile, string $locale, CarbonImmutable $at): array
    {
        $events = GameEvent::query()->where('status', 'registration')->where('closes_at', '>', $at)
            ->where('rules->version', 3)
            ->with(['entries' => fn (Relation $entries) => $entries->where('user_id', $owner->id)])
            ->orderBy('starts_at')->orderBy('id')->get();
        $dog = clone $pet;
        $dog->loadExists('activeDiseaseEpisodes');
        $dog->advanceTo($at, $this->decay);
        $states = $dog->statePercentages(null);
        $participations = $this->participation->entries($owner->id, [$pet->id], array_values($events->all()));
        $candidates = [];
        foreach ($events as $event) {
            if ($event->discipline === 'progeny') {
                continue;
            }
            $regulation = EventRegulationData::rules($event);
            foreach ($this->rules->entryOptions($event->discipline, $regulation, $profile, $pet->ageMonthsAt($event->starts_at)) as $option) {
                if (! $option['eligible']) {
                    continue;
                }
                $own = $event->entries->first();
                $sameDog = $own !== null && $own->pet_id === $pet->id && $own->status === 'registered';
                $registered = $sameDog && $this->rules->division($event->discipline, $regulation, $profile, $own->plan) === $option['categoryKey'];
                $reasons = $this->admission->blockingReasons(GameEventDiscipline::from($event->discipline), [
                    'active' => $dog->isActive(), 'retired' => $dog->retired_at !== null,
                    'ageMonths' => $pet->ageMonthsAt($event->starts_at), 'health' => $states['health'],
                    'hasDisease' => (bool) $dog->getAttribute('active_disease_episodes_exists'),
                    'energyPercentage' => $states['energy'], 'energy' => (float) $dog->energy,
                    'busy' => $dog->isBusy(), 'activityEndsAt' => $dog->activity_ends_at,
                ], ['startsAt' => $event->starts_at, 'closesAt' => $event->closes_at,
                    'energyCost' => (float) ($event->rules['energy_cost'] ?? 0),
                    'minimumMonths' => $this->rules->minimumMonths($event->discipline, $regulation, $option['plan'])]);
                $reason = isset($reasons[0]) ? __($reasons[0], [], $locale) : null;
                $participationReason = $this->participation->reason($owner->id, $pet->id, $event, $participations);
                if ($reason === null && $participationReason !== null) {
                    $reason = __($participationReason, [
                        'pet_daily_limit' => (int) config('game-events.pet_daily_limit'), 'pet_rest_hours' => (int) config('game-events.pet_rest_hours'),
                    ], $locale);
                }
                if ($event->registration_opens_at->isAfter($at)) {
                    $reason = __('title_progress.reasons.not_open', [], $locale);
                } elseif ($own !== null && ! $registered) {
                    $reason = __('title_progress.reasons.'.($sameDog ? 'different_class' : 'already_entered'), [], $locale);
                } elseif (! $registered && $owner->coins < ($event->rules['fee'] ?? 0)) {
                    $reason = __('title_progress.reasons.coins', ['amount' => $event->rules['fee'] ?? 0], $locale);
                }
                $labels = EventRegulationData::option($option, $locale, $pet->dog);
                $candidates[] = [
                    'event' => $event, 'regulation' => $regulation, 'plan' => $option['plan'],
                    'classLabel' => $labels['label'], 'registered' => $registered,
                    'petId' => $pet->id, 'optionKey' => $option['key'],
                    'eligible' => $reason === null || $registered, 'reason' => $reason,
                    'codes' => $this->opportunities->codes($event->discipline, $regulation, $profile, $option['plan']),
                ];
            }
        }

        return $candidates;
    }

    /**
     * @param  list<array<string,mixed>>  $goals
     * @param  list<array<string,mixed>>  $candidates
     * @param  array<string,mixed>  $profile
     * @param  list<array<string,mixed>>  $credentials
     * @return list<array<string,mixed>>
     */
    public function attach(array $goals, array $candidates, array $profile, string $locale, array $credentials = []): array
    {
        foreach ($goals as &$goal) {
            foreach ($goal['paths'] as &$path) {
                $path['events'] = [];
                $path['notes'] ??= [];
                if ($path['ready'] || (($goal['achieved'] ?? false) && ! $goal['repeatable'])) {
                    continue;
                }
                $targets = $this->expandTargets($path['targets'], $goal['discipline'], $goals);
                $path['events'] = $this->matching($targets, $candidates, $goal['code'], $goal['discipline'], $locale,
                    $this->needsCompatibleSet($path) && $goal['discipline'] !== 'conformation'
                        ? fn (array $candidate, string $code): bool => $this->completesCompatibleSet($candidate, $code, $goal, $path, $profile, $credentials)
                        : null);
                if ($path['events'] === [] && in_array($goal['discipline'], ['agility', 'nosework'], true)
                    && array_intersect(array_merge(...array_column($targets ?: [['codes' => []]], 'codes')),
                        ['CACAg', 'CACAg_BREED', 'REG_CACAg', 'CACIAG', 'CACNW', 'CACNW_BREED', 'REG_CACNW', 'SPORT_CH_RUS', 'SPORT_CUP_RUS', 'SPORT_CH_RKF', 'SPORT_BREED_RKF']) !== []) {
                    $qualifying = $this->qualificationTargets($goal['discipline'], $profile);
                    $path['events'] = $this->matching($qualifying, $candidates, $goal['code'], $goal['discipline'], $locale);
                    if ($path['events'] !== []) {
                        $path['notes'][] = __('title_progress.notes.prerequisite', [], $locale);
                    }
                }
                if ($path['events'] === []) {
                    $path['notes'][] = __('title_progress.notes.no_events', [], $locale);
                } else {
                    $path['notes'][] = __('title_progress.notes.conditional', [], $locale);
                    $path['notes'][] = __('title_progress.reasons.registration_check', [], $locale);
                }
            }
            unset($path);
        }
        unset($goal);

        return $goals;
    }

    /**
     * @param  list<array<string,mixed>>  $targets
     * @param  list<array<string,mixed>>  $goals
     * @param  list<string>  $visited
     * @return list<array<string,mixed>>
     */
    private function expandTargets(array $targets, string $discipline, array $goals, array $visited = []): array
    {
        $expanded = $targets;
        foreach ($targets as $target) {
            foreach ($target['codes'] as $code) {
                if (in_array($code, $visited, true)) {
                    continue;
                }
                foreach ($goals as $dependency) {
                    if ($dependency['code'] !== $code || $dependency['discipline'] !== $discipline) {
                        continue;
                    }
                    foreach ($dependency['paths'] as $path) {
                        foreach ($this->expandTargets($path['targets'], $discipline, $goals, [...$visited, $code]) as $leaf) {
                            $expanded[] = [...$leaf, ...array_diff_key($target, array_flip(['codes', 'via'])),
                                'excludeRegions' => array_values(array_unique([...($target['excludeRegions'] ?? []), ...($leaf['excludeRegions'] ?? [])])),
                                'excludeJudges' => array_values(array_unique([...($target['excludeJudges'] ?? []), ...($leaf['excludeJudges'] ?? [])])),
                                'via' => $code];
                        }
                    }
                }
            }
        }

        return $expanded;
    }

    /**
     * @param  list<array<string,mixed>>  $targets
     * @param  list<array<string,mixed>>  $candidates
     * @param  (callable(array<string,mixed>,string):bool)|null  $accept
     * @return list<array<string,mixed>>
     */
    private function matching(array $targets, array $candidates, string $goalCode, string $discipline, string $locale, ?callable $accept = null): array
    {
        $matches = [];
        foreach ($candidates as $candidate) {
            $event = $candidate['event'];
            foreach ($targets as $target) {
                if (! $this->matchesContext($target, $candidate)) {
                    continue;
                }
                foreach ($target['codes'] as $code) {
                    if ($event->discipline !== $discipline && ! ($goalCode === 'CIAG' && $code === 'SHOW_GRADE' && $event->discipline === 'conformation')) {
                        continue;
                    }
                    if (! isset($candidate['codes'][$code]) || ($goalCode === 'CIAG' && $code === 'SHOW_GRADE' && ($candidate['regulation']['rank'] ?? '') !== 'cacib')) {
                        continue;
                    }
                    if ((str_starts_with($code, 'REG_') && (int) ($candidate['regulation']['version'] ?? 1) < 2)
                        || ($accept !== null && ! $accept($candidate, $code))) {
                        continue;
                    }
                    if (($target['prerequisite'] ?? false) && ! $this->usefulQualification($code, $candidate, $target)) {
                        continue;
                    }
                    $metadata = EventRegulationData::fromEvent($event, $locale);
                    $name = implode(' · ', array_filter([
                        $metadata['specialShowLabel'] ?? __('events.disciplines.'.$event->discipline, [], $locale),
                        $metadata['showLegLabel'] ?? $metadata['rankLabel'] ?? null,
                    ]));
                    $result = $this->contribution($code, $candidate['codes'][$code], $locale);
                    if (isset($target['via'])) {
                        $result = __('title_progress.contribution.via', ['title' => $this->awardName($target['via'], $locale), 'result' => $result], $locale);
                    }
                    $key = $event->id.':'.$candidate['classLabel'];
                    $matches[$key] ??= [
                        'id' => $event->id, 'name' => $name, 'startsAt' => $event->starts_at->toIso8601String(),
                        'closesAt' => $event->closes_at->toIso8601String(), 'classLabel' => $candidate['classLabel'],
                        'contribution' => $result, 'eligible' => $candidate['eligible'], 'reason' => $candidate['reason'],
                        'registered' => $candidate['registered'],
                        'petId' => $candidate['petId'], 'optionKey' => $candidate['optionKey'],
                    ];
                    break;
                }
            }
        }
        $matches = array_values($matches);
        usort($matches, fn (array $first, array $second): int => [! $first['registered'], ! $first['eligible'], $first['startsAt'], $first['id']]
            <=> [! $second['registered'], ! $second['eligible'], $second['startsAt'], $second['id']]);

        return array_slice($matches, 0, self::RECOMMENDATIONS_PER_PATH);
    }

    /**
     * @param  array<string,mixed>  $target
     * @param  array<string,mixed>  $candidate
     */
    private function matchesContext(array $target, array $candidate): bool
    {
        $regulation = $candidate['regulation'];
        foreach (['region', 'country', 'showYear', 'showEdition', 'rank'] as $key) {
            if (isset($target[$key]) && ($regulation[$key] ?? null) !== $target[$key]) {
                return false;
            }
        }
        foreach (['specialShow', 'showLeg'] as $key) {
            if (isset($target[$key]) && ! in_array($regulation[$key] ?? '', $target[$key], true)) {
                return false;
            }
        }
        foreach (['excludeRegions' => 'region', 'excludeCountries' => 'country', 'excludeJudges' => 'judgeId'] as $targetKey => $key) {
            if (in_array($regulation[$key] ?? '', $target[$targetKey] ?? [], true)) {
                return false;
            }
        }

        return ! isset($target['notBefore']) || $candidate['event']->ends_at->getTimestamp() >= $target['notBefore'];
    }

    /**
     * @param  array<string,mixed>  $profile
     * @return list<array<string,mixed>>
     */
    private function qualificationTargets(string $discipline, array $profile): array
    {
        if ($discipline === 'agility' && ! in_array(3, $profile['agility_available_levels'] ?? [1], true)) {
            return [['codes' => ['AGILITY_QUALIFICATION'], 'prerequisite' => true, 'profile' => $profile]];
        }
        if ($discipline === 'nosework' && max($profile['nosework_levels'] ?? [0]) < 3) {
            return [['codes' => ['NW_ODOR_TEST', 'NW_CLASS_QUALIFICATION'], 'prerequisite' => true, 'profile' => $profile]];
        }

        return [];
    }

    /**
     * @param  array<string,mixed>  $candidate
     * @param  array<string,mixed>  $target
     */
    private function usefulQualification(string $code, array $candidate, array $target): bool
    {
        $program = $candidate['plan']['program'] ?? '';
        $odor = $candidate['plan']['test_type'] ?? '';

        return match ($code) {
            'NW_ODOR_TEST' => ! in_array($odor, $target['profile']['nosework_tests'][$program] ?? [], true),
            'NW_CLASS_QUALIFICATION' => in_array($candidate['plan']['sport_class'] ?? '', ['nw_0', 'nw_1', 'nw_2'], true)
                && ($target['profile']['nosework_levels'][$program] ?? 0) < 3,
            default => true,
        };
    }

    /** @param array<string,mixed> $path */
    private function needsCompatibleSet(array $path): bool
    {
        $missing = array_values(array_filter($path['requirements'], fn (array $requirement): bool => ! $requirement['met']));

        return count($missing) === 1 && $missing[0]['key'] === 'compatible_evidence';
    }

    /**
     * @param  array<string,mixed>  $candidate
     * @param  array<string,mixed>  $goal
     * @param  array<string,mixed>  $path
     * @param  array<string,mixed>  $profile
     * @param  list<array<string,mixed>>  $credentials
     */
    private function completesCompatibleSet(array $candidate, string $code, array $goal, array $path, array $profile, array $credentials): bool
    {
        $regulation = $candidate['regulation'];
        $proposed = [
            'title_id' => max([0, ...array_column($credentials, 'title_id')]) + 1,
            'entry_id' => max([0, ...array_column($credentials, 'entry_id')]) + 1,
            'code' => $code, 'kind' => str_starts_with($code, 'SPORT_') ? 'championship' : 'certificate',
            'scope_key' => '', 'discipline' => $goal['discipline'], 'regulation_version' => (string) ($regulation['version'] ?? 1),
            'awarded_at' => $candidate['event']->ends_at->getTimestamp(),
            'judgeId' => $regulation['judgeId'] ?? '', 'region' => $regulation['region'] ?? '',
            'country' => $regulation['country'] ?? '', 'rank' => $regulation['rank'] ?? '', 'details' => $regulation,
        ];
        foreach ($this->championships->progress($goal['discipline'], $profile, [...$credentials, $proposed], $proposed['awarded_at']) as $nextGoal) {
            if ($nextGoal['code'] === $goal['code'] && $nextGoal['scope_key'] === $goal['scope_key']) {
                foreach ($nextGoal['paths'] as $nextPath) {
                    if ($nextPath['key'] === $path['key'] && $nextPath['ready']) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /** @param array{rank:int,minimumParticipants:int} $opportunity */
    private function contribution(string $code, array $opportunity, string $locale): string
    {
        $key = match (true) {
            $code === 'SHOW_GRADE' => 'show_grade',
            str_ends_with($code, '_BREED') => 'breed_winner',
            in_array($code, ['AGILITY_QUALIFICATION', 'NW_ODOR_TEST', 'NW_CLASS_QUALIFICATION'], true) => 'qualify',
            in_array($code, ['EAW', 'EAJW', 'EAVW', 'RW', 'RJW', 'RVW', 'MW', 'MJW', 'MVW', 'CIS_W', 'CIS_JW', 'CIS_VW'], true)
                || preg_match('/^[JV]?(?:CH_RKF_CERT|CLUB_WINNER|BREED_WINNER)$/', $code) === 1 => 'comparison',
            default => 'result',
        };
        $text = __('title_progress.contribution.'.$key, ['certificate' => $this->awardName($code, $locale), 'rank' => $opportunity['rank']], $locale);
        if ($opportunity['minimumParticipants'] > 0) {
            $text .= ' '.__('title_progress.contribution.minimum', ['count' => $opportunity['minimumParticipants']], $locale);
        }

        return $text;
    }

    private function awardName(string $code, string $locale): string
    {
        $key = 'events.regulated_awards.'.$code;
        $name = __($key, [], $locale);

        return $name === $key ? $code : $name;
    }
}

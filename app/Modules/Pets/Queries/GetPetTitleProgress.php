<?php

namespace App\Modules\Pets\Queries;

use App\Models\Pet;
use App\Models\PetTitle;
use App\Models\User;
use App\Modules\Pets\Calculators\EventChampionshipRules;
use App\Modules\Pets\Calculators\GameEventCalendar;
use App\Modules\Pets\Calculators\RegionalSportChampionships;
use App\Modules\Pets\Calculators\SpecialExhibitionChampionships;
use App\Modules\Pets\DTO\EventTitleCredentialData;
use App\Modules\Pets\DTO\PetTitleProgressData;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class GetPetTitleProgress
{
    public function __construct(
        private GetPetEventProfile $profiles,
        private EventChampionshipRules $rules,
        private SpecialExhibitionChampionships $shows,
        private RegionalSportChampionships $sports,
        private GetTitleProgressEvents $events,
        private GameEventCalendar $calendar,
    ) {}

    /** @return array{generatedAt:string,goals:list<array<string,mixed>>} */
    public function handle(User $owner, Pet $pet, string $locale): array
    {
        $at = CarbonImmutable::now()->startOfSecond();
        $titles = $this->credentials($pet, $at);
        $pet->setRelation('titles', $titles);
        $profile = [...$this->profiles->forPet($pet), ...$this->calendar->editionAt($at)];
        $credentials = array_values($titles->map(EventTitleCredentialData::fromTitle(...))->all());
        $goals = [];
        foreach ($this->shows->progress($profile, $credentials, $at->getTimestamp()) as $goal) {
            $goals[] = [...$goal, 'discipline' => 'conformation'];
        }
        foreach (['agility', 'nosework', 'canicross'] as $discipline) {
            foreach ($this->sports->progress($discipline, $profile, $credentials, $at->getTimestamp()) as $goal) {
                $goals[] = [...$goal, 'discipline' => $discipline];
            }
        }
        foreach ($this->rules->progress('agility', $profile, $credentials, $at->getTimestamp()) as $goal) {
            if ($goal['code'] === 'CIAG') {
                $goals[] = [...$goal, 'discipline' => 'agility'];
            }
        }
        $candidates = $this->events->candidates($owner, $pet, $profile, $locale, $at);
        $goals = $this->events->attach($goals, $candidates, $profile, $locale, $credentials);
        foreach ($goals as &$goal) {
            $earned = $this->earned($goal, $titles);
            $goal['earnedCount'] = $earned->count();
            if (($goal['achieved'] ?? false) && (! $goal['repeatable'] || str_starts_with($goal['scope_key'], 'edition:'))) {
                $goal['status'] = 'achieved';
                $ids = $this->earnedProof($earned);
                $historical = $earned->filter(fn (PetTitle $title): bool => empty($title->details['credential_title_ids']))->pluck('id')->all();
                $goal['paths'] = [[
                    'key' => 'earned_documents', 'ready' => true, 'requirements' => [],
                    'proof_title_ids' => $ids, 'available_title_ids' => $historical, 'excluded' => [], 'targets' => [], 'events' => [],
                    'notes' => $historical === [] ? [] : [__('title_progress.notes.historical_proof', [], $locale)],
                ]];
            }
            if (($pet->retired_at !== null || ! $pet->isActive()) && ($goal['status'] ?? null) !== 'achieved') {
                $goal['status'] = 'unavailable';
                foreach ($goal['paths'] as &$path) {
                    $path['notes'][] = __('events.errors.archived', [], $locale);
                }
                unset($path);
            }
        }
        unset($goal);
        usort($goals, fn (array $first, array $second): int => $this->priority($first) <=> $this->priority($second));

        return PetTitleProgressData::fromGoals($goals, $titles->keyBy('id')->all(), $locale, $at->getTimestamp());
    }

    /** @return Collection<int,PetTitle> */
    private function credentials(Pet $pet, CarbonImmutable $at): Collection
    {
        return $pet->titles()->whereIn('regulation_version', ['1', '2'])->where('awarded_at', '<=', $at)
            ->whereIn('kind', ['certificate', 'qualification', 'comparison', 'championship'])
            ->whereHas('entry', fn (Builder $entries) => $entries->where('pet_id', $pet->id)->where('status', 'completed')->where('is_npc', false)
                ->whereHas('event', fn (Builder $events) => $events->where('status', 'settled')))
            ->with(['consumptions', 'entry:id,game_event_id'])->reorder()->orderBy('awarded_at')->orderBy('id')->get();
    }

    /**
     * @param  array<string,mixed>  $goal
     * @param  Collection<int,PetTitle>  $titles
     * @return Collection<int,PetTitle>
     */
    private function earned(array $goal, Collection $titles): Collection
    {
        return $titles->filter(function (PetTitle $title) use ($goal): bool {
            if ($title->kind !== 'championship' || $title->code !== $goal['code'] || $title->discipline !== $goal['discipline']) {
                return false;
            }
            $scope = $goal['scope_key'] ?? '';
            if ($scope === 'region:any') {
                return false;
            }
            if (str_starts_with($scope, 'region:')) {
                return mb_strtolower(trim((string) ($title->details['region'] ?? ''))) === substr($scope, strlen('region:'));
            }
            if (str_starts_with($scope, 'edition:')) {
                return $title->scope_key === $scope;
            }

            return true;
        });
    }

    /**
     * @param  Collection<int,PetTitle>  $earned
     * @return list<int>
     */
    private function earnedProof(Collection $earned): array
    {
        $ids = [];
        foreach ($earned as $title) {
            if (is_array($title->details['credential_title_ids'] ?? null)) {
                $ids = [...$ids, ...$title->details['credential_title_ids']];
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<string,mixed>  $goal
     * @return array{int,int,int,int,string,string}
     */
    private function priority(array $goal): array
    {
        $paths = $goal['paths'];
        $ready = count(array_filter($paths, fn (array $path): bool => $path['ready'])) > 0;
        $known = 0;
        $missing = PHP_INT_MAX;
        $events = 0;
        foreach ($paths as $path) {
            $known = max($known, count($path['available_title_ids']));
            $missing = min($missing, count(array_filter($path['requirements'], fn (array $requirement): bool => ! $requirement['met'])));
            $events += count($path['events']);
        }
        $achieved = ($goal['achieved'] ?? false) && ! $goal['repeatable'];

        return [(int) $achieved, (int) ! $ready, (int) ($known === 0), $events > 0 ? $missing : PHP_INT_MAX, $goal['discipline'], $goal['code']];
    }
}

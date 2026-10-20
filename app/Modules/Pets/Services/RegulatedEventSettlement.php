<?php

namespace App\Modules\Pets\Services;

use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Calculators\GameEventRandomness;
use App\Modules\Pets\Calculators\RegionalSportAwards;
use App\Modules\Pets\Calculators\RegulatedEventSimulator;
use App\Modules\Pets\Calculators\RegulatedExhibitionAwards;
use App\Modules\Pets\Calculators\RegulatedSportAwards;
use App\Modules\Pets\Calculators\SpecialExhibitionAwards;
use App\Modules\Pets\DTO\EventRegulationData;
use App\Modules\Pets\Enums\GameEventDiscipline;
use App\Modules\Players\DTO\PlayerProgressFact;
use App\Modules\Players\Services\PlayerProgress;
use App\Modules\Players\Services\PlayerWallet;
use Illuminate\Database\Eloquent\Collection;

final class RegulatedEventSettlement
{
    public function __construct(
        private RegulatedEventSimulator $simulator,
        private GameEventRandomness $randomness,
        private RegulatedExhibitionAwards $shows,
        private RegulatedSportAwards $sports,
        private RegionalSportAwards $regionalSports,
        private SpecialExhibitionAwards $specialShows,
        private EventTitleRecorder $titles,
        private RegulatedEventProgress $career,
        private PlayerWallet $wallet,
        private PlayerProgress $progress,
    ) {}

    /**
     * The processor owns the transaction and holds event, owner and pet locks.
     *
     * @param  Collection<int,User>  $owners
     * @param  Collection<int,Pet>  $pets
     * @return list<int>
     */
    public function settle(GameEvent $event, Collection $owners, Collection $pets): array
    {
        $entries = $event->entries()->where('status', 'frozen')->orderBy('id')->get();
        foreach ($entries as $entry) {
            $entry->result = $this->simulator->simulate($event->discipline, $entry->snapshot, $entry->plan, $event->rules,
                $this->randomness->draws($event->seed.':entry:'.$entry->operation_token, count($event->rules['stages']) * 2));
        }
        foreach ($entries->groupBy('division') as $participants) {
            $this->rank($event, $participants);
            if ($event->discipline === 'nosework' && (EventRegulationData::rules($event)['format'] ?? '') !== 'odor_test') {
                $this->noseworkStandings($participants);
            }
        }
        $protocol = $entries->map(static fn (GameEventEntry $entry): array => [
            'id' => $entry->id, 'division' => $entry->division, 'plan' => $entry->plan,
            'snapshot' => $entry->snapshot, 'result' => $entry->result, 'rank' => $entry->rank,
        ])->values()->all();
        $regulation = EventRegulationData::rules($event);
        $protocol = array_values($protocol);
        $expanded = ($regulation['version'] ?? 1) >= 2;
        $awards = $event->discipline === 'conformation'
            ? ($expanded ? $this->specialShows : $this->shows)->calculate($regulation, $protocol)
            : ($expanded ? $this->regionalSports : $this->sports)->calculate($event->discipline, $regulation, $protocol);
        foreach ($awards as $award) {
            $entry = $entries->firstWhere('id', $award['entry_id']);
            if ($entry === null || $entry->is_npc || ! $pets->has($entry->pet_id)) {
                continue;
            }
            $this->titles->record($event, $entry, $pets->get($entry->pet_id), $award);
        }
        $winners = [];
        foreach ($entries as $entry) {
            $owner = $owners->get($entry->user_id);
            $pet = $pets->get($entry->pet_id);
            $entry->status = 'completed';
            $entry->completed_at = $event->ends_at;
            $entry->prize = ! $entry->is_npc && $owner !== null && $entry->rank !== null
                ? ($event->rules['prizes'][$entry->rank - 1] ?? 0) : 0;
            $entry->save();
            if ($entry->is_npc || $owner === null || $pet === null) {
                continue;
            }
            if ($entry->prize > 0) {
                $this->wallet->change($owner, 'coins', $entry->prize, 'event-entry:'.$entry->id.':prize', 'event_prize');
            }
            $this->career->record($event, $entry, $pet);
            $won = $entry->rank === 1 && ($entry->result['qualified'] ?? false);
            if ($won) {
                $winners[] = $pet->id;
            }
            $show = GameEventDiscipline::from($event->discipline)->isExhibition();
            $this->progress->award($owner, $entry, fn (GameEventEntry $completed): PlayerProgressFact => new PlayerProgressFact(
                $show ? 'exhibition' : 'competition', $completed->completed_at,
                competitionWin: $won && ! $show, exhibitionWin: $won && $show,
            ), onlyAffectedAchievements: true);
            if ($pet->activity_token === $entry->operation_token) {
                $pet->clearActivity();
                $pet->last_activity_at = $event->ends_at;
                $pet->save();
            }
        }

        return $winners;
    }

    /** @param Collection<int,GameEventEntry> $participants */
    private function rank(GameEvent $event, Collection $participants): void
    {
        if ($event->discipline === 'agility') {
            $ties = $participants->filter(static fn (GameEventEntry $entry): bool => ! $entry->result['eliminated'])
                ->groupBy(static fn (GameEventEntry $entry): string => $entry->result['total_faults'].':'.$entry->result['time']);
            foreach ($ties->filter(static fn (Collection $group): bool => $group->count() > 1) as $group) {
                foreach ($group as $entry) {
                    $runoff = $this->simulator->simulate($event->discipline, $entry->snapshot, $entry->plan, $event->rules,
                        $this->randomness->draws($event->seed.':runoff:'.$entry->operation_token, count($event->rules['stages']) * 2));
                    $entry->result = [...$entry->result, 'runoff' => ['total_faults' => $runoff['eliminated'] ? PHP_INT_MAX : $runoff['total_faults'], 'time' => $runoff['time']]];
                }
            }
        }
        $ordered = $participants->sort(fn (GameEventEntry $first, GameEventEntry $second): int => $this->simulator->compareResults($event->discipline, $first->result, $second->result)
            ?: ($first->id <=> $second->id))->values();
        $odorTest = (EventRegulationData::rules($event)['format'] ?? '') === 'odor_test';
        $previous = null;
        $position = 0;
        foreach ($ordered as $entry) {
            $entry->rank = null;
            if ($odorTest || ! ($entry->result['qualified'] ?? false) || ($entry->result['eliminated'] ?? false)) {
                continue;
            }
            $position++;
            if ($event->discipline === 'conformation' && $position > config('event_regulations.regulations.shows.placements')) {
                continue;
            }
            $tied = $previous !== null && $this->simulator->compareResults($event->discipline, $previous->result, $entry->result) === 0;
            $entry->rank = $tied && in_array($event->discipline, ['nosework', 'agility', 'canicross'], true) ? $previous->rank : $position;
            $previous = $entry;
        }
    }

    /** @param Collection<int,GameEventEntry> $participants */
    private function noseworkStandings(Collection $participants): void
    {
        foreach ($participants as $entry) {
            $result = $entry->result;
            $standings = [[
                'key' => 'overall', 'rank' => $entry->rank, 'score' => $result['score'], 'time' => $result['time'],
                'penalties' => $result['penalties'], 'qualified' => $result['qualified'],
            ]];
            foreach ($result['stages'] as $index => $stage) {
                $ahead = $participants->filter(static function (GameEventEntry $other) use ($stage, $index): bool {
                    $candidate = $other->result['stages'][$index];

                    return $candidate['qualified'] && ($candidate['score'] > $stage['score']
                        || ($candidate['score'] === $stage['score'] && $candidate['found'] > $stage['found'])
                        || ($candidate['score'] === $stage['score'] && $candidate['found'] === $stage['found'] && $candidate['time'] < $stage['time']));
                })->count();
                $standings[] = [
                    'key' => $stage['key'], 'rank' => $stage['qualified'] ? $ahead + 1 : null,
                    'score' => $stage['score'], 'time' => $stage['time'], 'penalties' => $stage['penalties'], 'qualified' => $stage['qualified'],
                ];
            }
            $entry->result = [...$result, 'standings' => $standings];
        }
    }
}

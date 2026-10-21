<?php

namespace App\Modules\Pets\Queries;

use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\PetTitle;
use App\Models\User;
use App\Modules\Pets\DTO\GameEventDivisionData;
use App\Modules\Pets\DTO\PetTitleData;
use App\Modules\Pets\Enums\GameEventDiscipline;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Collection;

final class GetPetCareer
{
    public function __construct(private GetPetTitleProgress $titleProgress) {}

    /**
     * @return array{
     *     petId: int,
     *     titleProgress: array{generatedAt:string,goals:list<array<string,mixed>>},
     *     titles: list<array{name: string, discipline: string, frequency: string, awardedAt: string, count: int, eventId: int|null}>,
     *     summary: array{competitionStarts: int, competitionWins: int, exhibitionStarts: int, exhibitionWins: int, podiums: int, cups: int},
     *     results: array{entries: list<array{id: int, eventId: int, kind: string, discipline: string, frequency: string, division: string, divisionLabel: string, rank: int, prize: int, completedAt: string, petName: string, eliminated: bool}>, nextCursor: string|null, previousCursor: string|null}
     * }
     */
    public function handle(User $user, int $petId, string $locale, ?Cursor $cursor = null): array
    {
        $pet = $user->pets()->with('dog')->findOrFail($petId);
        $titleProgress = $this->titleProgress->handle($user, $pet, $locale);
        $entries = GameEventEntry::query()->involvingPets([$pet->id])
            ->where(fn (Builder $visible) => $visible->where('game_event_entries.pet_id', $pet->id)
                ->orWhereHas('event', fn (Builder $events) => $events->whereIn('discipline', ['couple', 'breeders'])))
            ->where('game_event_entries.is_npc', false)->where('game_event_entries.status', 'completed')
            ->whereNotNull('game_event_entries.completed_at')->whereNotNull('game_event_entries.result')
            ->whereHas('event', fn (Builder $events) => $events->where('status', 'settled'));
        $page = (clone $entries)->with('event:id,discipline,frequency')
            ->select(['id', 'game_event_id', 'pet_id', 'division', 'rank', 'prize', 'completed_at', 'result'])
            ->selectRaw("snapshot->>'name' AS historical_name")
            ->selectRaw("result->>'eliminated' AS was_eliminated")
            ->orderByDesc('completed_at')->orderByDesc('id')->cursorPaginate(20, ['*'], 'career_cursor', $cursor);

        return [
            'petId' => $pet->id,
            'titleProgress' => $titleProgress,
            'titles' => $this->titles($pet, $entries, $locale),
            'summary' => $this->summary($entries),
            'results' => [
                'entries' => array_values(array_map(fn (GameEventEntry $entry): array => [
                    'id' => $entry->id, 'eventId' => $entry->game_event_id,
                    'kind' => GameEventDiscipline::from($entry->event->discipline)->isExhibition() ? 'exhibition' : 'competition',
                    'discipline' => $entry->event->discipline, 'frequency' => $entry->event->frequency,
                    'divisionLabel' => GameEventDivisionData::label($entry->division, $locale, $pet->dog),
                    'division' => $entry->division, 'rank' => $entry->rank, 'prize' => $entry->pet_id === $pet->id ? $entry->prize : 0,
                    'completedAt' => $entry->completed_at->toIso8601String(),
                    'petName' => $this->historicalMemberName($entry, $pet),
                    'eliminated' => $entry->getAttribute('was_eliminated') !== 'false',
                ], $page->items())),
                'nextCursor' => $page->nextCursor()?->encode(),
                'previousCursor' => $page->previousCursor()?->encode(),
            ],
        ];
    }

    private function historicalMemberName(GameEventEntry $entry, Pet $pet): string
    {
        if ($entry->pet_id === $pet->id) {
            $name = $entry->getAttribute('historical_name');

            return is_string($name) ? $name : $pet->name;
        }
        $members = $entry->result['members'] ?? [];
        if (is_array($members)) {
            foreach ($members as $member) {
                if (is_array($member) && ($member['id'] ?? null) === $pet->id && is_string($member['name'] ?? null)) {
                    return $member['name'];
                }
            }
        }

        return $pet->name;
    }

    /**
     * @param  Builder<GameEventEntry>  $entries
     * @return list<array{name: string, discipline: string, frequency: string, awardedAt: string, count: int, eventId: int|null}>
     */
    private function titles(Pet $pet, Builder $entries, string $locale): array
    {
        $validResults = (clone $entries)->where('game_event_entries.result->eliminated', false);
        $identity = ['discipline', 'code', 'class_key', 'sex', 'kind'];
        $context = [
            'award_frequency' => "CASE WHEN kind = 'legacy' THEN frequency ELSE NULL END",
            'award_scope' => "CASE WHEN kind = 'championship' AND code = 'SPORT_REGIONAL_CH' THEN COALESCE(details->>'region', '') ELSE scope_key END",
            'award_show_year' => "details->>'showYear'",
            'award_show_edition' => "details->>'showEdition'",
            'award_program' => "details->>'program'",
            'award_odor' => "details->>'odor'",
            'award_format' => "details->>'format'",
            'award_height' => "details->>'heightCategory'",
            'award_race_category' => "details->>'race_category'",
        ];
        $partition = implode(', ', [...$identity, ...array_values($context)]);
        $groupEntries = (clone $entries)->whereHas('event', fn (Builder $events) => $events->whereIn('discipline', ['couple', 'breeders'])
            ->where('rules->version', 3)->whereIn('rules->regulation->format', ['couple_ring', 'breeders_ring']));
        $query = PetTitle::query()->where(fn (Builder $titles) => $titles->where('pet_id', $pet->id)
            ->orWhere(fn (Builder $groupAwards) => $groupAwards->whereIn('game_event_entry_id', (clone $groupEntries)->select('game_event_entries.id'))
                ->whereIn('discipline', ['couple', 'breeders'])->whereIn('kind', ['comparison', 'placement'])))
            ->whereIn('game_event_entry_id', (clone $entries)->select('game_event_entries.id'))
            ->where(fn (Builder $awards) => $awards->whereIn('kind', ['championship', 'qualification'])
                ->orWhereIn('game_event_entry_id', $validResults->select('game_event_entries.id')))
            ->select('pet_titles.*')->selectRaw('count(*) over (partition by '.$partition.') AS awards_count');
        foreach ($context as $alias => $expression) {
            $query->selectRaw($expression.' AS '.$alias);
        }
        $distinct = [...$identity, ...array_keys($context)];
        $query->distinct($distinct)->reorder();
        foreach ($distinct as $column) {
            $query->orderBy($column);
        }
        $titles = $query->orderByDesc('awarded_at')->orderByDesc('id')->with('entry:id,game_event_id')->get()
            ->sortByDesc('awarded_at')->values();
        $pet->setRelation('titles', $titles);

        return array_map(fn (array $data, PetTitle $title): array => [
            ...$data, 'count' => (int) $title->getAttribute('awards_count'), 'eventId' => $title->entry?->game_event_id,
        ], PetTitleData::fromPet($pet, $locale), $titles->all());
    }

    /**
     * @param  Builder<GameEventEntry>  $entries
     * @return array{competitionStarts: int, competitionWins: int, exhibitionStarts: int, exhibitionWins: int, podiums: int, cups: int}
     */
    private function summary(Builder $entries): array
    {
        /** @var Collection<int, object{discipline: string, starts: int|string, wins: int|string, podiums: int|string, cups: int|string}> $statistics */
        $statistics = (clone $entries)->join('game_events', 'game_events.id', '=', 'game_event_entries.game_event_id')
            ->select('game_events.discipline')->selectRaw('count(*) AS starts')
            ->selectRaw("count(case when game_event_entries.rank = 1 and game_event_entries.result->>'eliminated' = 'false' then 1 end) AS wins")
            ->selectRaw("count(case when game_event_entries.rank between 1 and 3 and game_event_entries.result->>'eliminated' = 'false' then 1 end) AS podiums")
            ->selectRaw("count(case when (game_events.rules->>'version' = '3' and game_events.rules->'regulation'->>'rank' = 'cup' or game_events.rules->>'version' <> '3' and game_events.frequency = 'weekly') and game_event_entries.rank = 1 and game_event_entries.result->>'eliminated' = 'false' then 1 end) AS cups")
            ->groupBy('game_events.discipline')->toBase()->get();
        $summary = ['competitionStarts' => 0, 'competitionWins' => 0, 'exhibitionStarts' => 0, 'exhibitionWins' => 0, 'podiums' => 0, 'cups' => 0];
        foreach ($statistics as $statistic) {
            if (GameEventDiscipline::from($statistic->discipline)->isExhibition()) {
                $summary['exhibitionStarts'] += (int) $statistic->starts;
                $summary['exhibitionWins'] += (int) $statistic->wins;
            } else {
                $summary['competitionStarts'] += (int) $statistic->starts;
                $summary['competitionWins'] += (int) $statistic->wins;
            }
            $summary['podiums'] += (int) $statistic->podiums;
            $summary['cups'] += (int) $statistic->cups;
        }

        return $summary;
    }
}

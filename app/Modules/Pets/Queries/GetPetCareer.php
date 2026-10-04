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
    /**
     * @return array{
     *     petId: int,
     *     titles: list<array{name: string, discipline: string, frequency: string, awardedAt: string, count: int, eventId: int|null}>,
     *     summary: array{competitionStarts: int, competitionWins: int, exhibitionStarts: int, exhibitionWins: int, podiums: int, cups: int},
     *     results: array{entries: list<array{id: int, eventId: int, kind: string, discipline: string, frequency: string, division: string, divisionLabel: string, rank: int, prize: int, completedAt: string, petName: string, eliminated: bool}>, nextCursor: string|null, previousCursor: string|null}
     * }
     */
    public function handle(User $user, int $petId, string $locale, ?Cursor $cursor = null): array
    {
        $pet = $user->pets()->with('dog')->findOrFail($petId);
        $entries = GameEventEntry::query()->where('game_event_entries.pet_id', $pet->id)
            ->where('game_event_entries.is_npc', false)->where('game_event_entries.status', 'completed')
            ->whereNotNull('game_event_entries.completed_at')->whereNotNull('game_event_entries.result')
            ->where('game_event_entries.rank', '>', 0)
            ->whereHas('event', fn (Builder $events) => $events->where('status', 'settled'));
        $page = (clone $entries)->with('event:id,discipline,frequency')
            ->select(['id', 'game_event_id', 'division', 'rank', 'prize', 'completed_at'])
            ->selectRaw("snapshot->>'name' AS historical_name")
            ->selectRaw("result->>'eliminated' AS was_eliminated")
            ->orderByDesc('completed_at')->orderByDesc('id')->cursorPaginate(20, ['*'], 'career_cursor', $cursor);

        return [
            'petId' => $pet->id,
            'titles' => $this->titles($pet, $entries, $locale),
            'summary' => $this->summary($entries),
            'results' => [
                'entries' => array_values(array_map(fn (GameEventEntry $entry): array => [
                    'id' => $entry->id, 'eventId' => $entry->game_event_id,
                    'kind' => GameEventDiscipline::from($entry->event->discipline)->isExhibition() ? 'exhibition' : 'competition',
                    'discipline' => $entry->event->discipline, 'frequency' => $entry->event->frequency,
                    'divisionLabel' => GameEventDivisionData::label($entry->division, $locale, $pet->dog),
                    'division' => $entry->division, 'rank' => (int) $entry->rank, 'prize' => $entry->prize,
                    'completedAt' => $entry->completed_at->toIso8601String(),
                    'petName' => (string) ($entry->getAttribute('historical_name') ?? $pet->name),
                    'eliminated' => $entry->getAttribute('was_eliminated') !== 'false',
                ], $page->items())),
                'nextCursor' => $page->nextCursor()?->encode(),
                'previousCursor' => $page->previousCursor()?->encode(),
            ],
        ];
    }

    /**
     * @param  Builder<GameEventEntry>  $entries
     * @return list<array{name: string, discipline: string, frequency: string, awardedAt: string, count: int, eventId: int|null}>
     */
    private function titles(Pet $pet, Builder $entries, string $locale): array
    {
        $wins = (clone $entries)->where('game_event_entries.rank', 1)->where('game_event_entries.result->eliminated', false);
        $titles = $pet->titles()->whereIn('game_event_entry_id', $wins->select('game_event_entries.id'))
            ->select('pet_titles.*')->selectRaw('count(*) over (partition by discipline, frequency) AS awards_count')
            ->distinct(['discipline', 'frequency'])->reorder()->orderBy('discipline')->orderBy('frequency')
            ->orderByDesc('awarded_at')->orderByDesc('id')->with('entry:id,game_event_id')->get()
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
            ->selectRaw("count(case when game_events.frequency = 'weekly' and game_event_entries.rank = 1 and game_event_entries.result->>'eliminated' = 'false' then 1 end) AS cups")
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

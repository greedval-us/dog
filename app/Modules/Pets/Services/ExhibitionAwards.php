<?php

namespace App\Modules\Pets\Services;

use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\PetTitle;
use App\Modules\Pets\Calculators\GameEventSimulator;
use App\Modules\Pets\Enums\ExhibitionClass;
use LogicException;

final class ExhibitionAwards
{
    public function __construct(private GameEventSimulator $simulator) {}

    public function recordPlace(GameEvent $event, GameEventEntry $entry): void
    {
        if ($entry->rank !== null && $entry->rank <= 3 && ! ($entry->result['eliminated'] ?? true)) {
            $this->record($event, $entry, 'conformation_class_place_'.$entry->rank);
        }
    }

    public function recordBreedTitles(GameEvent $event): void
    {
        $finalists = $event->entries()->where('status', 'completed')->where('rank', 1)
            ->where('result->eliminated', false)->orderBy('id')->get()
            ->filter(fn (GameEventEntry $entry): bool => ExhibitionClass::tryFrom($entry->snapshot['exhibition_class'] ?? '')?->competesForBreedTitles() ?? false)
            ->groupBy(fn (GameEventEntry $entry): int => (int) $entry->snapshot['breed_id']);

        foreach ($finalists as $breedEntries) {
            $bestOfSex = [];
            foreach (['male', 'female'] as $sex) {
                $winner = $breedEntries->filter(fn (GameEventEntry $entry): bool => ($entry->snapshot['sex'] ?? null) === $sex)
                    ->sort(fn (GameEventEntry $first, GameEventEntry $second): int => $this->compare($event, $first, $second))->first();
                if ($winner !== null) {
                    $this->record($event, $winner, 'conformation_best_'.$sex);
                    $bestOfSex[] = $winner;
                }
            }
            usort($bestOfSex, fn (GameEventEntry $first, GameEventEntry $second): int => $this->compare($event, $first, $second));
            if ($bestOfSex !== []) {
                $this->record($event, $bestOfSex[0], 'conformation_best_of_breed');
            }
        }
    }

    private function compare(GameEvent $event, GameEventEntry $first, GameEventEntry $second): int
    {
        return $this->simulator->compareResults($event->discipline, $this->comparisonResult($first), $this->comparisonResult($second), $event->rules['version'])
            ?: strcmp(hash_hmac('sha256', 'rank:'.$first->operation_token, $event->seed), hash_hmac('sha256', 'rank:'.$second->operation_token, $event->seed))
            ?: ($first->id <=> $second->id);
    }

    /** @return array{eliminated:bool,penalties:int,score:int|float,time:int|float} */
    private function comparisonResult(GameEventEntry $entry): array
    {
        $result = $entry->result;
        if ($result === null || ! isset($result['eliminated'], $result['penalties'], $result['score'], $result['time'])
            || ! is_bool($result['eliminated']) || ! is_int($result['penalties'])
            || (! is_int($result['score']) && ! is_float($result['score']))
            || (! is_int($result['time']) && ! is_float($result['time']))) {
            throw new LogicException('Breed finalists must have a completed competition result.');
        }

        return ['eliminated' => $result['eliminated'], 'penalties' => $result['penalties'], 'score' => $result['score'], 'time' => $result['time']];
    }

    private function record(GameEvent $event, GameEventEntry $entry, string $code): void
    {
        if ($entry->is_npc || $entry->pet_id === null || $entry->user_id === null) {
            return;
        }
        PetTitle::query()->firstOrCreate(['game_event_entry_id' => $entry->id, 'code' => $code], [
            'pet_id' => $entry->pet_id, 'game_event_id' => $event->id, 'discipline' => $event->discipline,
            'frequency' => $event->frequency, 'awarded_at' => $event->ends_at,
            'breed_id' => $entry->snapshot['breed_id'], 'class_key' => $entry->snapshot['exhibition_class'],
            'sex' => $entry->snapshot['sex'], 'age_months' => $entry->snapshot['age_months'], 'rank' => $entry->rank,
        ]);
    }
}

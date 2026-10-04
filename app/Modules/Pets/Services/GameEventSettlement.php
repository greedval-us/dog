<?php

namespace App\Modules\Pets\Services;

use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\PetSportRecord;
use App\Models\PetTitle;
use App\Models\User;
use App\Modules\Pets\Calculators\GameEventRandomness;
use App\Modules\Pets\Calculators\GameEventSimulator;
use App\Modules\Pets\Enums\GameEventDiscipline;
use App\Modules\Players\DTO\PlayerProgressFact;
use App\Modules\Players\Services\PlayerProgress;
use App\Modules\Players\Services\PlayerWallet;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

final class GameEventSettlement
{
    public function __construct(
        private PlayerWallet $wallet,
        private PlayerProgress $progress,
        private GameEventSimulator $simulator,
        private GameEventRandomness $randomness,
    ) {}

    /**
     * The processor owns the transaction and holds event, owner and pet locks.
     *
     * @param  Collection<int, User>  $owners
     * @param  Collection<int, Pet>  $pets
     */
    public function settle(GameEvent $event, Collection $owners, Collection $pets, CarbonImmutable $at): void
    {
        foreach ($event->entries()->where('status', 'frozen')->get()->groupBy('division') as $participants) {
            $results = [];
            foreach ($participants as $entry) {
                $results[$entry->id] = $this->simulator->simulate(
                    $event->discipline, $entry->snapshot, $entry->plan, $event->rules,
                    $this->randomness->draws($event->seed.':entry:'.$entry->operation_token, 6),
                );
            }
            $ordered = $participants->sort(fn (GameEventEntry $first, GameEventEntry $second): int => $this->simulator->compareResults($event->discipline, $results[$first->id], $results[$second->id], $event->rules['version'])
                ?: strcmp($first->operation_token, $second->operation_token))->values();
            foreach ($ordered as $index => $entry) {
                $entry->result = $results[$entry->id];
                $entry->rank = $index + 1;
                $entry->status = 'completed';
                $entry->completed_at = $event->ends_at;
                $owner = $owners->get($entry->user_id);
                $pet = $pets->get($entry->pet_id);
                $prize = $entry->is_npc || $owner === null || $entry->result['eliminated'] ? 0 : ($event->rules['prizes'][$index] ?? 0);
                $entry->prize = $prize;
                $entry->save();
                if (! $entry->is_npc && $owner !== null && $pet !== null) {
                    if ($prize > 0) {
                        $this->wallet->change($owner, 'coins', $prize, 'event-entry:'.$entry->id.':prize', 'event_prize');
                    }
                    $this->recordCareer($event, $entry, $pet);
                    $this->progress->award($owner, $entry, function (GameEventEntry $completed) use ($event): PlayerProgressFact {
                        $show = GameEventDiscipline::from($event->discipline)->isExhibition();
                        $won = $completed->rank === 1 && ! ($completed->result['eliminated'] ?? true);

                        return new PlayerProgressFact(
                            $show ? 'exhibition' : 'competition',
                            $completed->status === 'completed' && ! $completed->is_npc ? $completed->completed_at : null,
                            competitionWin: $won && ! $show,
                            exhibitionWin: $won && $show,
                        );
                    }, onlyAffectedAchievements: true);
                    if ($pet->activity_token === $entry->operation_token) {
                        $pet->clearActivity();
                        $pet->last_activity_at = $event->ends_at;
                        $pet->save();
                    }
                }
            }
        }
        $event->update(['status' => 'settled', 'settled_at' => $at]);
    }

    private function recordCareer(GameEvent $event, GameEventEntry $entry, Pet $pet): void
    {
        $win = $entry->rank === 1 && ! $entry->result['eliminated'];
        $record = PetSportRecord::query()->firstOrCreate(['pet_id' => $pet->id, 'discipline' => $event->discipline], ['starts' => 0, 'wins' => 0, 'experience' => 0, 'tier' => 0]);
        $record->starts++;
        $record->wins += (int) $win;
        $record->experience = min(1000000, $record->experience + ($entry->result['eliminated'] ? 2 : ($win ? 10 : 5)));
        $record->tier = max($record->tier, $record->experience >= 90 ? 2 : ($record->experience >= 30 ? 1 : 0));
        $record->save();
        if ($win) {
            PetTitle::query()->firstOrCreate(['game_event_entry_id' => $entry->id], [
                'pet_id' => $pet->id, 'discipline' => $event->discipline, 'frequency' => $event->frequency,
                'code' => $event->discipline.'_'.$event->frequency.'_winner', 'awarded_at' => $event->ends_at,
            ]);
        }
    }
}

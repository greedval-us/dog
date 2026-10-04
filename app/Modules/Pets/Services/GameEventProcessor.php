<?php

namespace App\Modules\Pets\Services;

use App\Models\DogWorkShift;
use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\PetCareAction;
use App\Models\PetSportRecord;
use App\Models\PetTitle;
use App\Models\User;
use App\Modules\Inventory\Services\InventoryConsumption;
use App\Modules\Pets\Actions\CompleteDogWork;
use App\Modules\Pets\Calculators\GameEventSimulator;
use App\Modules\Pets\Enums\PetActivity;
use App\Modules\Pets\Exceptions\GameEventUnavailable;
use App\Modules\Players\Enums\PlayerStatus;
use App\Modules\Players\Services\PlayerProgress;
use App\Modules\Players\Services\PlayerWallet;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class GameEventProcessor
{
    public function __construct(private GameEventAdmission $admission, private PetLifecycle $lifecycle, private InventoryConsumption $inventory, private PlayerWallet $wallet, private PlayerProgress $progress, private GameEventSimulator $simulator, private PetCareCompletion $careCompletion, private CompleteDogWork $workCompletion) {}

    public function processDue(?CarbonImmutable $at = null, ?User $owner = null): int
    {
        $at = ($at ?? CarbonImmutable::now())->startOfSecond();
        $query = GameEvent::query()->whereIn('status', ['registration', 'frozen'])->where('closes_at', '<=', $at);
        if ($owner !== null) {
            $query->whereHas('entries', fn ($entries) => $entries->where('user_id', $owner->id));
        }
        $count = 0;
        foreach ($query->orderBy('starts_at')->orderBy('id')->limit(100)->get() as $event) {
            $count += $this->process($event->id, $at) ? 1 : 0;
        }

        return $count;
    }

    public function processForOwner(User $user, ?CarbonImmutable $at = null): int
    {
        return $this->processDue($at, $user);
    }

    private function process(int $eventId, CarbonImmutable $at): bool
    {
        $thresholds = [];

        return DB::transaction(function () use ($eventId, $at, &$thresholds): bool {
            $event = GameEvent::query()->lockForUpdate()->findOrFail($eventId);
            if (! in_array($event->status, ['registration', 'frozen'], true)) {
                return false;
            }
            $entries = $event->entries()->orderBy('id')->get();
            $owners = User::query()->whereIn('id', $entries->pluck('user_id')->filter())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $pets = Pet::query()->whereIn('id', $entries->pluck('pet_id')->filter())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($event->status === 'registration') {
                foreach ($entries->where('status', 'registered') as $entry) {
                    $owner = $owners->get($entry->user_id);
                    $pet = $pets->get($entry->pet_id);
                    if ($owner !== null && $owner->status === PlayerStatus::Active && $pet !== null && $event->discipline !== 'progeny') {
                        $care = PetCareAction::query()->where('pet_id', $pet->id)->where('activity_token', $pet->activity_token)->whereNull('completed_at')->whereNull('cancelled_at')->where('ends_at', '<=', $event->closes_at)->lockForUpdate()->first();
                        if ($care !== null) {
                            $this->careCompletion->complete($owner, $pet, $care, $event->closes_at, $thresholds);
                        }
                        $shift = DogWorkShift::query()->where('pet_id', $pet->id)->where('activity_token', $pet->activity_token)->whereNull('completed_at')->whereNull('cancelled_at')->where('ends_at', '<=', $event->closes_at)->lockForUpdate()->first();
                        if ($shift !== null) {
                            $this->workCompletion->handle($owner, $shift->token, $event->closes_at);
                            $pet->refresh();
                        }
                        $this->lifecycle->synchronizeOwner($owner, $event->closes_at);
                        $pet->refresh();
                    }
                    $reason = $owner === null || $owner->status !== PlayerStatus::Active || $pet === null || $pet->user_id !== $owner->id
                        ? 'events.errors.unavailable' : $this->admission->reason($event, $pet, $event->closes_at);
                    if ($reason === null && $event->discipline !== 'progeny' && $pet->isBusy()) {
                        $reason = 'events.errors.busy';
                    }
                    $gear = [];
                    if ($reason === null) {
                        try {
                            $this->admission->plan($event, $pet, $entry->plan);
                            $gear = $this->admission->gear($owner, $event, $pet, $entry->gear_ids, true);
                        } catch (GameEventUnavailable $exception) {
                            $reason = $exception->getMessage();
                        }
                    }
                    if ($reason !== null) {
                        $this->withdraw($entry, $owner, $reason, $at, $pet);

                        continue;
                    }
                    $entry->snapshot = $this->admission->snapshot($event, $pet, $entry->plan, $gear);
                    $entry->status = 'frozen';
                    $entry->save();
                    foreach ($gear as $item) {
                        $this->inventory->handle($owner, $item['id'], $this->uuid($event->seed.':usage:'.$entry->id.':'.$item['id']));
                    }
                    if ($event->discipline !== 'progeny') {
                        $pet->energy = max(0, $pet->energy - $event->rules['energy_cost']);
                        $pet->activity = in_array($event->discipline, ['conformation', 'progeny'], true) ? PetActivity::Exhibition : PetActivity::Competition;
                        $pet->activity_token = $entry->operation_token;
                        $pet->activity_started_at = $event->closes_at;
                        $pet->activity_ends_at = $event->ends_at;
                        $pet->save();
                    }
                }
                $groups = $event->entries()->where('status', 'frozen')->get()->groupBy('division');
                foreach ($groups as $division => $humans) {
                    if ($humans->count() > $event->rules['field_size']) {
                        foreach ($humans->slice($event->rules['field_size']) as $entry) {
                            $this->withdraw($entry, $owners->get($entry->user_id), 'events.errors.full', $at, $pets->get($entry->pet_id));
                        }
                    }
                    $reference = $humans->first()->snapshot;
                    for ($index = min($humans->count(), $event->rules['field_size']); $index < $event->rules['field_size']; $index++) {
                        $key = $event->seed.':npc:'.$division.':'.$index;
                        GameEventEntry::query()->create([
                            'game_event_id' => $event->id, 'is_npc' => true, 'operation_token' => $this->uuid($key.':event:'.$event->id),
                            'registration_hash' => hash('sha256', $key), 'division' => $division, 'status' => 'frozen',
                            'fee' => 0, 'gear_ids' => [], 'plan' => ['stages' => ['balanced', 'careful', 'bold']],
                            'snapshot' => $this->npc($reference, $division, $key, $index),
                        ]);
                    }
                }
                $event->status = $groups->isEmpty() ? 'cancelled' : 'frozen';
                $event->save();
            }
            if ($event->status !== 'frozen' || $at->lessThan($event->ends_at)) {
                return true;
            }
            foreach ($event->entries()->where('status', 'frozen')->get()->groupBy('division') as $participants) {
                foreach ($participants as $entry) {
                    $entry->result = $this->simulator->simulate($event->discipline, $entry->snapshot, $entry->plan, $event->rules, $this->draws($event->seed.':entry:'.$entry->operation_token, 6));
                }
                $ordered = $participants->sort(function (GameEventEntry $first, GameEventEntry $second) use ($event): int {
                    return ($first->result['eliminated'] <=> $second->result['eliminated'])
                        ?: (in_array($event->discipline, ['agility', 'nosework'], true)
                            ? ($first->result['penalties'] <=> $second->result['penalties'])
                            : ($second->result['score'] <=> $first->result['score']))
                        ?: ($first->result['time'] <=> $second->result['time'])
                        ?: strcmp($first->operation_token, $second->operation_token);
                })->values();
                foreach ($ordered as $index => $entry) {
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
                        $this->record($event, $entry, $pet);
                        $this->progress->award($owner, $entry);
                        if ($pet->activity_token === $entry->operation_token) {
                            $pet->clearActivity();
                            $pet->last_activity_at = $event->ends_at;
                            $pet->save();
                        }
                    }
                }
            }
            $event->update(['status' => 'settled', 'settled_at' => $at]);

            return true;
        }, attempts: 3);
    }

    private function withdraw(GameEventEntry $entry, ?User $owner, string $reason, CarbonImmutable $at, ?Pet $pet = null): void
    {
        if ($owner !== null && $entry->fee > 0) {
            $this->wallet->change($owner, 'coins', $entry->fee, 'event-entry:'.$entry->id.':refund', 'event_refund');
        }
        $entry->update(['status' => 'withdrawn', 'refunded_at' => $at, 'result' => ['reason' => $reason]]);
        if ($pet !== null && $pet->activity_token === $entry->operation_token) {
            $pet->clearActivity();
            $pet->save();
        }
    }

    private function record(GameEvent $event, GameEventEntry $entry, Pet $pet): void
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

    /** @return list<float> */
    private function draws(string $key, int $count): array
    {
        $draws = [];
        for ($index = 0; $index < $count; $index++) {
            $draws[] = hexdec(substr(hash('sha256', $key.':'.$index), 0, 8)) / 4294967295;
        }

        return $draws;
    }

    private function uuid(string $key): string
    {
        $hash = hash('sha256', $key);

        return substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-4'.substr($hash, 13, 3).'-8'.substr($hash, 17, 3).'-'.substr($hash, 20, 12);
    }

    /**
     * @param  array<string, mixed>  $reference
     * @return array<string, mixed>
     */
    private function npc(array $reference, string $division, string $key, int $index): array
    {
        $draws = $this->draws($key, 12);
        $tier = str_starts_with($division, 'champion') ? 2 : (str_starts_with($division, 'open') ? 1 : 0);
        $stats = [];
        foreach (['endurance', 'speed', 'strength', 'agility', 'obedience', 'intelligence'] as $offset => $stat) {
            $stats[$stat] = (int) round(25 + $tier * 35 + $draws[$offset] * 30);
        }
        $exterior = ['type' => 60 + $draws[6] * 25, 'structure' => 60 + $draws[7] * 25, 'movement' => 60 + $draws[8] * 25];

        return [
            'name' => 'NPC #'.($index + 1), 'breed' => $reference['breed'], 'breed_id' => $reference['breed_id'], 'size' => $reference['size'],
            'stats' => $stats, 'potentials' => array_fill_keys(array_keys($stats), 140),
            'states' => ['health' => 100, 'energy' => 90, 'satiety' => 90, 'hydration' => 90, 'mood' => 85, 'cleanliness' => 90, 'bond' => 70],
            'skills' => ['keen_nose' => $tier + 1, 'search' => $tier + 1], 'exterior' => $exterior,
            'career_experience' => $tier * 40, 'gear' => [], 'modifiers' => [],
            'offspring' => array_fill(0, 3, ['exterior' => $exterior, 'titles' => $tier > 0 ? [['code' => 'conformation_daily_winner']] : []]),
        ];
    }
}

<?php

namespace App\Modules\Pets\Services;

use App\Models\DogWorkShift;
use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\PetCareAction;
use App\Models\User;
use App\Modules\Inventory\Services\InventoryConsumption;
use App\Modules\Pets\Actions\CompleteDogWork;
use App\Modules\Pets\Calculators\GameEventRandomness;
use App\Modules\Pets\Enums\GameEventDiscipline;
use App\Modules\Pets\Enums\PetActivity;
use App\Modules\Pets\Exceptions\GameEventUnavailable;
use App\Modules\Pets\Generators\GameEventNpcGenerator;
use App\Modules\Players\Enums\PlayerStatus;
use App\Modules\Players\Services\PlayerWallet;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

final class GameEventFreezing
{
    public function __construct(
        private GameEventAdmission $admission,
        private PetLifecycle $lifecycle,
        private InventoryConsumption $inventory,
        private PlayerWallet $wallet,
        private PetCareCompletion $careCompletion,
        private CompleteDogWork $workCompletion,
        private GameEventRandomness $randomness,
        private GameEventNpcGenerator $npcs,
    ) {}

    /**
     * The processor owns the transaction and holds event, owner and pet locks.
     *
     * @param  Collection<int, GameEventEntry>  $entries
     * @param  Collection<int, User>  $owners
     * @param  Collection<int, Pet>  $pets
     * @param  array<string, int>  $thresholds  Care draws retained across transaction retries.
     */
    public function freeze(GameEvent $event, Collection $entries, Collection $owners, Collection $pets, CarbonImmutable $at, array &$thresholds): void
    {
        $discipline = GameEventDiscipline::from($event->discipline);
        foreach ($entries->where('status', 'registered') as $entry) {
            $owner = $owners->get($entry->user_id);
            $pet = $pets->get($entry->pet_id);
            if ($owner !== null && $owner->status === PlayerStatus::Active && $pet !== null && ! $discipline->isDocumentary()) {
                $this->completePriorActivity($owner, $pet, $event->closes_at, $thresholds);
            }
            $reason = $owner === null || $owner->status !== PlayerStatus::Active || $pet === null || $pet->user_id !== $owner->id
                ? 'events.errors.unavailable' : $this->admission->reason($event, $pet, $event->closes_at);
            if ($reason === null && ! $discipline->isDocumentary() && $pet->isBusy()) {
                $reason = 'events.errors.busy';
            }
            $gear = [];
            if ($reason === null) {
                try {
                    $gear = $this->admission->prepare($owner, $event, $pet, $entry->plan, $entry->gear_ids, lockGear: true)['gear'];
                } catch (GameEventUnavailable $exception) {
                    $reason = $exception->getMessage();
                }
            }
            if ($reason !== null || $owner === null || $pet === null) {
                $this->withdraw($entry, $owner, $reason ?? 'events.errors.unavailable', $at, $pet);

                continue;
            }
            $this->freezeEntry($event, $entry, $owner, $pet, $gear);
        }

        $groups = $event->entries()->where('status', 'frozen')->orderBy('id')->get()->groupBy('division');
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
                    'game_event_id' => $event->id, 'is_npc' => true, 'operation_token' => $this->randomness->token($key.':event:'.$event->id),
                    'registration_hash' => hash('sha256', $key), 'division' => $division, 'status' => 'frozen',
                    'fee' => 0, 'gear_ids' => [], 'plan' => ['stages' => ['balanced', 'careful', 'bold']],
                    'snapshot' => $this->npcs->generate($reference, $division, $key, $index),
                ]);
            }
        }
        $event->status = $groups->isEmpty() ? 'cancelled' : 'frozen';
        $event->save();
    }

    /** @param array<string, int> $thresholds */
    private function completePriorActivity(User $owner, Pet $pet, CarbonImmutable $closedAt, array &$thresholds): void
    {
        $care = PetCareAction::query()->where('pet_id', $pet->id)->where('activity_token', $pet->activity_token)
            ->whereNull('completed_at')->whereNull('cancelled_at')->where('ends_at', '<=', $closedAt)->lockForUpdate()->first();
        if ($care !== null) {
            $this->careCompletion->complete($owner, $pet, $care, $closedAt, $thresholds);
        }
        $shift = DogWorkShift::query()->where('pet_id', $pet->id)->where('activity_token', $pet->activity_token)
            ->whereNull('completed_at')->whereNull('cancelled_at')->where('ends_at', '<=', $closedAt)->lockForUpdate()->first();
        if ($shift !== null) {
            $this->workCompletion->handle($owner, $shift->token, $closedAt);
            $pet->refresh();
        }
        $this->lifecycle->synchronizeOwner($owner, $closedAt);
        $pet->refresh();
    }

    /** @param list<array<string, mixed>> $gear */
    private function freezeEntry(GameEvent $event, GameEventEntry $entry, User $owner, Pet $pet, array $gear): void
    {
        $entry->snapshot = $this->admission->snapshot($event, $pet, $entry->plan, $gear);
        $entry->status = 'frozen';
        $entry->save();
        foreach ($gear as $item) {
            $this->inventory->handle($owner, $item['id'], $this->randomness->token($event->seed.':usage:'.$entry->id.':'.$item['id']));
        }
        $discipline = GameEventDiscipline::from($event->discipline);
        if (! $discipline->isDocumentary()) {
            $pet->energy = max(0, $pet->energy - $event->rules['energy_cost']);
            $pet->activity = $discipline->isExhibition() ? PetActivity::Exhibition : PetActivity::Competition;
            $pet->activity_token = $entry->operation_token;
            $pet->activity_started_at = $event->closes_at;
            $pet->activity_ends_at = $event->ends_at;
            $pet->save();
        }
    }

    private function withdraw(GameEventEntry $entry, ?User $owner, string $reason, CarbonImmutable $at, ?Pet $pet): void
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
}

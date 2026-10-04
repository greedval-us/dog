<?php

namespace App\Modules\Pets\Actions;

use App\Models\PetHistoryEntry;
use App\Models\PetHistoryEvent;
use App\Models\PetThoughtState;
use App\Models\User;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\Calculators\PetThoughtRules;
use App\Modules\Pets\Services\PetLifecycleSynchronization;
use App\Modules\Players\Enums\PlayerStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class RecordPetThought
{
    public function __construct(private PetDecayCalculator $decay, private PetThoughtRules $rules, private PetLifecycleSynchronization $lifecycle) {}

    public function handle(User $user, int $petId, ?CarbonImmutable $at = null): ?PetHistoryEntry
    {
        $at ??= now()->startOfSecond();
        $this->lifecycle->synchronizeOwner($user, $at);

        return DB::transaction(function () use ($user, $petId, $at): ?PetHistoryEntry {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);
            $pet = $owner->pets()->lockForUpdate()->findOrFail($petId);
            $this->lifecycle->assertCanAdvance($owner, $at);
            if ($owner->status !== PlayerStatus::Active || ! $pet->isActive()) {
                return null;
            }
            $last = PetThoughtState::query()->where('pet_id', $pet->id)->max('last_occurred_at');
            if ($last !== null && CarbonImmutable::parse($last)->addMinutes(15)->greaterThan($at)) {
                return null;
            }
            $pet->advanceTo($at, $this->decay);
            if (! $pet->isActive()) {
                $this->lifecycle->persist($pet);

                return null;
            }
            $values = $pet->statePercentages(precision: null);
            $values['activity'] = $pet->activity_ends_at !== null && $pet->activity_ends_at->greaterThan($at)
                ? ($pet->activity->value ?? 'idle') : 'idle';
            $values['has_disease'] = $pet->activeDiseaseEpisodes()->exists();
            $events = PetHistoryEvent::query()->where('kind', 'thought')->where('is_active', true)
                ->with(['phrases' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('id')])
                ->orderByDesc('priority')->orderBy('id')->get();
            $states = PetThoughtState::query()->where('pet_id', $pet->id)->get()->keyBy('pet_history_event_id');

            foreach ($events as $event) {
                if (! $this->rules->matches($event->conditions ?? [], $values)) {
                    continue;
                }
                $state = $states->get($event->id);
                if ($state !== null && $state->last_occurred_at->addMinutes(max(15, $event->cooldown_minutes))->greaterThan($at)) {
                    continue;
                }
                $phrases = $event->phrases;
                $position = $phrases->search(fn ($phrase): bool => $phrase->id === $state?->pet_history_phrase_id);
                $phrase = $phrases->isEmpty() ? null : $phrases->values()->get($position === false ? 0 : ($position + 1) % $phrases->count());
                if ($phrase === null) {
                    continue;
                }
                $entry = PetHistoryEntry::query()->create([
                    'pet_id' => $pet->id, 'pet_history_event_id' => $event->id, 'pet_history_phrase_id' => $phrase->id,
                    'kind' => 'thought', 'event_code' => $event->code,
                    'source_key' => 'thought:'.$event->id.':'.$at->getTimestamp(),
                    'title' => $event->name, 'message' => $phrase->text, 'occurred_at' => $at,
                ]);
                PetThoughtState::query()->updateOrCreate(['pet_id' => $pet->id, 'pet_history_event_id' => $event->id], [
                    'pet_history_phrase_id' => $phrase->id, 'last_occurred_at' => $at,
                ]);

                return $entry;
            }

            return null;
        }, 3);
    }
}

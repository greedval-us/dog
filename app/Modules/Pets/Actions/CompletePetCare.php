<?php

namespace App\Modules\Pets\Actions;

use App\Models\PetCareAction;
use App\Models\User;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\Calculators\PetStatusRules;
use App\Modules\Pets\Enums\PetStat;
use App\Modules\Pets\Enums\PetState;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Services\PetActivityManager;
use App\Modules\Pets\Services\PetDiseaseTracker;
use App\Modules\Pets\Services\PetStateSynchronizer;
use App\Modules\Players\Enums\PlayerStatus;
use Illuminate\Support\Facades\DB;

final class CompletePetCare
{
    public function __construct(
        private PetActivityManager $activities,
        private PetStatusRules $statuses,
        private PetDecayCalculator $states,
        private PetStateSynchronizer $state,
        private PetDiseaseTracker $diseases,
    ) {}

    public function handle(User $user, int $petId, string $token): bool
    {
        $thresholds = [];

        return DB::transaction(function () use ($user, $petId, $token, &$thresholds): bool {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($owner->status !== PlayerStatus::Active) {
                throw new PetUnavailable('This player cannot care for pets.');
            }

            $pet = $owner->pets()->lockForUpdate()->findOrFail($petId);
            $care = PetCareAction::query()->where('user_id', $owner->id)->where('pet_id', $petId)->where('token', strtolower($token))->lockForUpdate()->firstOrFail();

            if ($care->completed_at !== null) {
                return false;
            }

            if (! $this->activities->complete($owner, $petId, $care->activity_token)) {
                throw new PetUnavailable('This activity is not ready to finish.');
            }

            $at = now();
            $endedAt = $care->ends_at;
            $pet->advanceTo($endedAt, $this->states);

            foreach ($care->effects as $name => $percentage) {
                $state = PetState::from($name);
                $maximum = $pet->getAttribute($state->maximumColumn());
                $value = $pet->getAttribute($name) + $maximum * $percentage / 100;
                $pet->setAttribute($name, round(max(0, min($maximum, $value)), 4));
            }

            foreach ($care->stat_gains ?? [] as $name => $gain) {
                $stat = PetStat::from($name);
                $pet->setAttribute($name, min($pet->getAttribute($stat->potentialColumn()), $pet->getAttribute($name) + $gain));
            }

            $pet->debuffs = $this->statuses->recover($pet->debuffs ?? [], $care->status_recovery ?? [], $endedAt->getTimestamp());
            $awards = [...($care->granted_effects ?? []), ...array_column($care->incidents ?? [], 'effect')];
            foreach (['buff' => 'buffs', 'debuff' => 'debuffs'] as $kind => $column) {
                $grants = array_values(array_filter($awards, fn (array $effect): bool => $effect['kind'] === $kind));
                $pet->setAttribute($column, $this->statuses->award($pet->getAttribute($column) ?? [], $grants, $endedAt->getTimestamp(), $endedAt->getTimestamp()));
            }
            $pet->debuffs = [...($pet->debuffs ?? []), ...$this->diseases->record($pet, $care, $thresholds)];
            $this->state->advance($pet, $at);
            $pet->save();
            $care->completed_at = $at;
            $care->save();

            return true;
        }, attempts: 3);
    }
}

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
use App\Modules\Pets\Services\PetHistoryRecorder;
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
        private PetHistoryRecorder $history,
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
            $changes = [];

            foreach ($care->effects as $name => $percentage) {
                $state = PetState::from($name);
                $maximum = $pet->getAttribute($state->maximumColumn());
                $before = $pet->getAttribute($name);
                $value = $before + $maximum * $percentage / 100;
                $pet->setAttribute($name, round(max(0, min($maximum, $value)), 4));
                $after = $pet->getAttribute($name);
                $changes[] = [
                    'metric' => $name, 'before' => round($before / $maximum * 100, 4),
                    'after' => round($after / $maximum * 100, 4),
                    'delta' => round(($after - $before) / $maximum * 100, 4), 'unit' => 'percent',
                ];
            }

            foreach ($care->stat_gains ?? [] as $name => $gain) {
                $stat = PetStat::from($name);
                $before = $pet->getAttribute($name);
                $pet->setAttribute($name, min($pet->getAttribute($stat->potentialColumn()), $before + $gain));
                $after = $pet->getAttribute($name);
                $changes[] = ['metric' => $name, 'before' => (float) $before,
                    'after' => (float) $after, 'delta' => (float) ($after - $before), 'unit' => 'points'];
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
            $this->history->record($pet, $care->group === 'training' ? 'training' : 'care.'.$care->variant,
                'care:'.$care->id.':completed', $endedAt, [
                    'stage' => 'completed', 'name' => $care->training_name,
                    'durationSeconds' => max(0, $endedAt->getTimestamp() - ($care->created_at?->getTimestamp() ?? $endedAt->getTimestamp())),
                    'changes' => $changes, 'confirmedAt' => $at->toIso8601String(),
                    'statusRecovery' => $care->status_recovery ?? [],
                    'awardedEffects' => $awards,
                ]);

            return true;
        }, attempts: 3);
    }
}

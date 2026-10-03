<?php

namespace App\Modules\Pets\Services;

use App\Models\Pet;
use App\Models\PetCareAction;
use App\Models\User;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\Calculators\PetStatusRules;
use App\Modules\Pets\Enums\PetStat;
use App\Modules\Pets\Enums\PetState;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Players\Services\PlayerProgress;
use Carbon\CarbonImmutable;

final class PetCareCompletion
{
    public function __construct(
        private PetActivityManager $activities,
        private PetStatusRules $statuses,
        private PetDecayCalculator $decay,
        private PetStateSynchronizer $state,
        private PetDiseaseTracker $diseases,
        private PetHistoryRecorder $history,
        private PlayerProgress $progress,
    ) {}

    /**
     * Settle care at its finish time before advancing the pet further. The caller holds owner, pet and receipt locks.
     *
     * @param  array<string, int>  $thresholds  Random draws retained across transaction retries.
     */
    public function complete(User $owner, Pet $pet, PetCareAction $care, CarbonImmutable $confirmedAt, array &$thresholds): bool
    {
        if ($care->completed_at !== null || $care->cancelled_at !== null) {
            return false;
        }
        if (! $pet->isActive()) {
            return false;
        }
        if ($care->ends_at->greaterThan($confirmedAt) || $pet->activity_token !== $care->activity_token) {
            throw new PetUnavailable('This activity is not ready to finish.');
        }

        $endedAt = $care->ends_at;
        $pet->advanceTo($endedAt, $this->decay);
        if (! $pet->isActive()) {
            return false;
        }
        if (! $this->activities->complete($owner, $pet->id, $care->activity_token, $endedAt)) {
            throw new PetUnavailable('This activity is not ready to finish.');
        }
        $pet->clearActivity();
        $pet->last_activity_at = $endedAt;
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
        $this->state->synchronize($pet, $endedAt);
        $pet->save();
        $care->completed_at = $confirmedAt;
        $care->save();
        $experienceAwarded = $this->progress->award($owner, $care);
        $this->history->record($pet, $care->group === 'training' ? 'training' : 'care.'.$care->variant,
            'care:'.$care->id.':completed', $endedAt, [
                'stage' => 'completed', 'name' => $care->training_name, 'experienceAwarded' => $experienceAwarded,
                'durationSeconds' => max(0, $endedAt->getTimestamp() - ($care->created_at?->getTimestamp() ?? $endedAt->getTimestamp())),
                'changes' => $changes, 'confirmedAt' => $confirmedAt->toIso8601String(),
                'statusRecovery' => $care->status_recovery ?? [], 'awardedEffects' => $awards,
            ]);

        return true;
    }
}

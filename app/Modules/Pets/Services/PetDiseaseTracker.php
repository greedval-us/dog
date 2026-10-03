<?php

namespace App\Modules\Pets\Services;

use App\Models\Disease;
use App\Models\Pet;
use App\Models\PetCareAction;
use App\Models\PetDiseaseCounter;
use Random\Randomizer;

/** @phpstan-import-type Effect from \App\Modules\Pets\Calculators\PetStatusRules */
final class PetDiseaseTracker
{
    public function __construct(private Randomizer $random) {}

    /**
     * Record a completed action inside the caller's transaction with the owner and pet locked.
     *
     * @param  array<string, int>  $thresholds  Random draws retained across transaction retries.
     * @return list<Effect>
     */
    public function record(Pet $pet, PetCareAction $care, array &$thresholds): array
    {
        $day = $care->ends_at->setTimezone(config('doglive.disease_timezone'))->toDateString();
        $activeIds = $pet->activeDiseaseEpisodes()->pluck('disease_id')->all();
        $effects = [];

        foreach (Disease::query()->where('is_active', true)->orderBy('id')->get() as $disease) {
            $rules = $disease->acquisition_rules;
            if ($rules === null || $rules['daily_min'] < 1 || $rules['daily_max'] < $rules['daily_min']
                || ($rules['group'] === null && $rules['variants'] === [])
                || ($rules['group'] !== null && $rules['group'] !== $care->group)
                || ($rules['variants'] !== [] && ! in_array($care->variant, $rules['variants'], true))) {
                continue;
            }

            $counter = PetDiseaseCounter::query()->lockForUpdate()->firstOrNew(['pet_id' => $pet->id, 'disease_id' => $disease->id]);
            if (! $counter->exists || $counter->tracked_on->toDateString() !== $day) {
                $key = $pet->id.':'.$disease->id.':'.$day;
                $thresholds[$key] ??= $this->random->getInt($rules['daily_min'], $rules['daily_max']);
                $counter->fill(['tracked_on' => $day, 'action_count' => 0, 'threshold' => $thresholds[$key]]);
            }
            $counter->action_count++;
            $counter->save();

            if ($counter->action_count < $counter->threshold || in_array($disease->id, $activeIds, true)) {
                continue;
            }

            $effect = [...$disease->snapshot(), 'starts_at' => $care->ends_at->getTimestamp()];
            $pet->diseaseEpisodes()->create([
                'disease_id' => $disease->id,
                'started_at' => $care->ends_at,
                'effect_snapshot' => $effect,
            ]);
            $effects[] = $effect;
        }

        return $effects;
    }
}

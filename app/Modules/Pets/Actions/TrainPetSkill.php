<?php

namespace App\Modules\Pets\Actions;

use App\Models\CurrencyTransaction;
use App\Models\PetSkill;
use App\Models\PetSkillLesson;
use App\Models\Skill;
use App\Models\User;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\Calculators\SkillRules;
use App\Modules\Pets\DTO\TrainPetSkillData;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Queries\GetPetStatSnapshot;
use App\Modules\Pets\Services\PetHistoryRecorder;
use App\Modules\Pets\Services\PetLifecycle;
use App\Modules\Players\Enums\PlayerStatus;
use App\Modules\Players\Exceptions\InsufficientFunds;
use App\Modules\Players\Services\PlayerProgress;
use App\Modules\Players\Services\PlayerWallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class TrainPetSkill
{
    public function __construct(private PlayerWallet $wallet, private SkillRules $rules, private PetDecayCalculator $decay,
        private GetPetStatSnapshot $getAttributes, private PetHistoryRecorder $history, private PlayerProgress $progress,
        private PetLifecycle $lifecycle) {}

    public function handle(User $user, int $petId, TrainPetSkillData $data): PetSkillLesson
    {
        $token = strtolower($data->token);
        if (! Str::isUuid($token) || $petId < 1 || $data->skillId < 1 || $data->level < 1
            || $data->level > SkillRules::MAX_LEVEL || $data->expectedPrice < 1) {
            throw new PetUnavailable('Invalid skill lesson.');
        }
        $this->lifecycle->synchronizeOwner($user);

        return DB::transaction(function () use ($user, $petId, $data, $token): PetSkillLesson {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($owner->status !== PlayerStatus::Active) {
                throw new PetUnavailable('Your account is blocked.');
            }

            $existing = PetSkillLesson::query()->where('user_id', $owner->id)->where('token', $token)->first();
            if ($existing !== null) {
                if ($existing->pet_id !== $petId || $existing->skill_id !== $data->skillId || $existing->level !== $data->level
                    || $existing->price_paid !== $data->expectedPrice) {
                    throw new PetUnavailable('This token was already used for a different skill lesson.');
                }

                return $existing;
            }

            $operationKey = 'skill-lesson:'.$token;
            if (CurrencyTransaction::query()->whereBelongsTo($owner)->where('operation_key', $operationKey)->exists()) {
                throw new PetUnavailable('This lesson was already paid for, but its receipt is unavailable.');
            }

            $pet = $owner->pets()->lockForUpdate()->findOrFail($petId);
            if (! $pet->isActive() && $pet->retired_at === null) {
                throw new PetUnavailable('This dog is no longer active.');
            }
            if ($pet->retired_at !== null) {
                throw new PetUnavailable('Retired dogs cannot learn skills.');
            }
            if ($pet->isBusy()) {
                throw new PetUnavailable('Finish your dog’s current activity before a skill lesson.');
            }

            $skill = Skill::query()->sharedLock()->find($data->skillId);
            if ($skill === null || ! $skill->is_active || ! $this->rules->valid($skill->levels)) {
                throw new PetUnavailable('This skill is unavailable.');
            }
            $progress = PetSkill::query()->where('pet_id', $pet->id)->where('skill_id', $skill->id)->first();
            $currentLevel = $progress->level ?? 0;
            if ($currentLevel >= SkillRules::MAX_LEVEL) {
                throw new PetUnavailable('Your dog has mastered all five levels of this skill.');
            }
            if ($data->level !== $currentLevel + 1) {
                throw new PetUnavailable('Learn skill levels in order. Refresh the page.');
            }

            $at = now()->startOfSecond();
            if ($progress?->cooldown_until?->greaterThan($at)) {
                throw new PetUnavailable('Wait 24 hours between lessons for the same skill.');
            }

            $level = $skill->levels[$currentLevel];
            if ($level['price'] !== $data->expectedPrice) {
                throw new PetUnavailable('The lesson price has changed. Refresh the page.');
            }
            $pet->advanceTo($at, $this->decay);
            if (! $pet->isActive()) {
                throw new PetUnavailable('This dog is no longer active.');
            }
            $attributes = $this->getAttributes->handle($pet);
            if (! $this->rules->meets($attributes, $level['requirements'])) {
                throw new PetUnavailable('Raise your dog’s attributes to the lesson requirements.');
            }

            try {
                $entry = $this->wallet->change($owner, 'coins', -$level['price'], $operationKey, 'skill_lesson');
            } catch (InsufficientFunds) {
                throw new PetUnavailable('You do not have enough coins to pay the instructor.');
            }

            $cooldownUntil = $at->addSeconds(SkillRules::COOLDOWN_SECONDS);
            $pet->skills()->syncWithoutDetaching([$skill->id => [
                'level' => $data->level, 'last_trained_at' => $at, 'cooldown_until' => $cooldownUntil,
            ]]);
            $pet->save();

            $lesson = PetSkillLesson::query()->create([
                'user_id' => $owner->id, 'pet_id' => $pet->id, 'skill_id' => $skill->id,
                'level' => $data->level, 'price_paid' => $level['price'], 'requirements' => $this->rules->requiredValues($attributes, $level['requirements']),
                'token' => $token, 'trained_at' => $at, 'cooldown_until' => $cooldownUntil,
                'currency_transaction_id' => $entry->id,
            ]);
            $experienceAwarded = $this->progress->award($owner, $lesson);
            $this->history->record($pet, 'skill_training', 'skill:'.$lesson->id.':completed', $at, [
                'stage' => 'completed', 'name' => $skill->name, 'level' => $data->level, 'experienceAwarded' => $experienceAwarded,
                'durationSeconds' => 0, 'coins' => -$lesson->price_paid,
                'changes' => [['metric' => 'skill_level', 'before' => (float) $currentLevel,
                    'after' => (float) $data->level, 'delta' => 1.0, 'unit' => 'points']],
            ]);

            return $lesson;
        }, attempts: 3);
    }
}

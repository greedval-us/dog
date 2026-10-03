<?php

namespace App\Modules\Pets\Actions;

use App\Models\DogWorkOffer;
use App\Models\DogWorkShift;
use App\Models\PetSkill;
use App\Models\Skill;
use App\Models\User;
use App\Modules\Pets\Calculators\DogWorkRules;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\Calculators\SkillRules;
use App\Modules\Pets\DTO\PetHistoryChange;
use App\Modules\Pets\DTO\StartDogWorkData;
use App\Modules\Pets\Enums\PetActivity;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Queries\GetPetStatSnapshot;
use App\Modules\Pets\Services\PetActivityManager;
use App\Modules\Pets\Services\PetHistoryRecorder;
use App\Modules\Pets\Services\PetLifecycleSynchronization;
use App\Modules\Players\Enums\PlayerStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class StartDogWork
{
    public function __construct(private PetActivityManager $activities, private SkillRules $skills,
        private DogWorkRules $rules, private PetDecayCalculator $decay, private GetPetStatSnapshot $getAttributes,
        private PetHistoryRecorder $history, private PetLifecycleSynchronization $lifecycle) {}

    public function handle(User $user, int $petId, StartDogWorkData $data): DogWorkShift
    {
        $token = strtolower($data->token);
        if ($petId < 1 || $data->offerId < 1 || ! Str::isUuid($token)) {
            throw new PetUnavailable('Invalid dog work request.');
        }
        $this->lifecycle->synchronizeOwner($user);

        return DB::transaction(function () use ($user, $petId, $data, $token): DogWorkShift {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($owner->status !== PlayerStatus::Active) {
                throw new PetUnavailable('Your account is blocked.');
            }
            $existing = DogWorkShift::query()->where('user_id', $owner->id)->where('token', $token)->first();
            if ($existing !== null) {
                if ($existing->pet_id !== $petId || $existing->dog_work_offer_id !== $data->offerId) {
                    throw new PetUnavailable('This token was already used for different dog work.');
                }

                return $existing;
            }

            $pet = $owner->pets()->lockForUpdate()->findOrFail($petId);
            if (! $pet->isActive() && $pet->retired_at === null) {
                throw new PetUnavailable('This dog is no longer active.');
            }
            $offer = DogWorkOffer::query()->with('board')->lockForUpdate()->find($data->offerId);
            if ($offer === null || $offer->board->work_date->toDateString() !== now(config('doglive.work_timezone'))->toDateString()
                || ! $this->rules->valid($offer->required_skill_level, $offer->coins_reward, $offer->gems_reward,
                    $offer->duration_seconds, $offer->energy_cost, $offer->daily_limit)) {
                throw new PetUnavailable('This job is no longer available. Refresh the board.');
            }
            if (DogWorkShift::query()->where('user_id', $owner->id)->where('dog_work_offer_id', $offer->id)->exists()) {
                throw new PetUnavailable('You have already taken this job today.');
            }
            if ($offer->reserved_count >= $offer->daily_limit) {
                throw new PetUnavailable('All places for this job have been taken.');
            }
            if ($pet->retired_at !== null) {
                throw new PetUnavailable('Retired dogs cannot work.');
            }
            if ($pet->isBusy()) {
                throw new PetUnavailable('Finish your dog’s current activity before starting work.');
            }

            $skill = Skill::query()->sharedLock()->find($offer->required_skill_id);
            $progress = PetSkill::query()->where('pet_id', $pet->id)->where('skill_id', $offer->required_skill_id)->first();
            $level = $progress->level ?? 0;
            if ($skill === null || ! $this->skills->hasLearnedLevel($skill->levels, $skill->is_active, $level, $offer->required_skill_level)) {
                throw new PetUnavailable('Your dog needs the required skill level.');
            }
            $at = now()->startOfSecond();
            $pet->advanceTo($at, $this->decay);
            if (! $pet->isActive()) {
                throw new PetUnavailable('This dog is no longer active.');
            }
            $attributes = $this->getAttributes->handle($pet);
            if (! $this->skills->isActive($attributes, $skill->levels, $skill->is_active, $level, $pet->retired_at !== null)) {
                throw new PetUnavailable('Raise your dog’s attributes to reactivate this skill.');
            }
            if ($pet->energy < $offer->energy_cost) {
                throw new PetUnavailable('Your dog does not have enough energy for this job.');
            }

            $pet->save();
            $activity = $this->activities->start($owner, $pet->id, PetActivity::Work, $at->addSeconds($offer->duration_seconds), $offer->energy_cost);
            $reserved = DogWorkOffer::query()->whereKey($offer->id)->whereColumn('reserved_count', '<', 'daily_limit')->increment('reserved_count');
            if ($reserved !== 1) {
                throw new PetUnavailable('All places for this job have been taken.');
            }

            $shift = DogWorkShift::query()->create([
                'user_id' => $owner->id, 'pet_id' => $pet->id, 'pet_name' => $pet->name, 'dog_work_offer_id' => $offer->id,
                'token' => $token, 'activity_token' => $activity->token, 'name' => $offer->name,
                'coins_reward' => $offer->coins_reward, 'gems_reward' => $offer->gems_reward,
                'started_at' => $activity->startedAt, 'ends_at' => $activity->endsAt,
            ]);
            $energyBefore = $pet->energy / $pet->energy_max * 100;
            $energyAfter = ($pet->energy - $offer->energy_cost) / $pet->energy_max * 100;
            $this->history->record($pet, 'work', 'work:'.$shift->id.':started', $activity->startedAt, [
                'stage' => 'started', 'name' => $shift->name, 'durationSeconds' => $offer->duration_seconds,
                'changes' => $offer->energy_cost === 0 ? [] : [PetHistoryChange::percent('energy', $energyBefore, $energyAfter)->toArray()],
            ]);

            return $shift;
        }, attempts: 3);
    }
}

<?php

namespace App\Modules\Players\Services;

use App\Models\DogWorkShift;
use App\Models\PetCareAction;
use App\Models\PetSkillLesson;
use App\Models\User;
use App\Models\VeterinaryVisit;
use App\Modules\Players\Calculators\PlayerLevelRules;
use Brick\Math\BigInteger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Public cross-module operation for completed dog actions and lifetime player progress. */
final class PlayerProgress
{
    /** Call inside the gameplay transaction so the action, reward and counters commit together. */
    public function award(User $user, PetCareAction|DogWorkShift|PetSkillLesson|VeterinaryVisit $receipt): int
    {
        if (! $receipt->exists) {
            throw new InvalidArgumentException('Player progress requires a saved action receipt.');
        }

        return DB::transaction(function () use ($user, $receipt): int {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);
            /** @var PetCareAction|DogWorkShift|PetSkillLesson|VeterinaryVisit $completed */
            $completed = $receipt->newQuery()->lockForUpdate()->findOrFail($receipt->getKey());

            if ($completed->user_id !== $owner->id || $this->completedAt($completed) === null) {
                throw new InvalidArgumentException('Player progress requires a completed action owned by the player.');
            }

            if ($completed->experience_awarded !== null) {
                return $completed->experience_awarded;
            }

            $code = $this->eventCode($completed);
            $rewards = config('player-progress.rewards', []);
            $experience = $rewards[$code] ?? config('player-progress.default_experience', 10);

            if (! is_int($experience) || $experience < 0) {
                throw new InvalidArgumentException('Player experience rewards must be non-negative integers.');
            }

            $totalExperience = (string) BigInteger::of($owner->experience)->plus($experience);
            $progress = PlayerLevelRules::progress($totalExperience);
            $statistics = $owner->pet_statistics;
            $statistics[$code] = ($statistics[$code] ?? 0) + 1;
            $awardedAt = CarbonImmutable::now();
            $dayTimezone = config('doglive.work_timezone', 'Europe/Moscow');

            $owner->forceFill([
                'experience' => $totalExperience,
                'level' => $progress['level'],
                'pet_statistics' => $statistics,
                'walks_count' => $owner->walks_count + (int) ($completed instanceof PetCareAction && $completed->group === 'walk'),
                'trainings_count' => $owner->trainings_count + (int) ($code === 'training' || $code === 'skill_training'),
                'active_days' => $owner->active_days + (int) ($owner->last_pet_action_at?->setTimezone($dayTimezone)->toDateString() !== $awardedAt->setTimezone($dayTimezone)->toDateString()),
                'last_pet_action_at' => $awardedAt,
            ])->save();

            $completed->forceFill(['experience_awarded' => $experience])->save();
            $receipt->setAttribute('experience_awarded', $experience);

            return $experience;
        }, attempts: 3);
    }

    private function eventCode(PetCareAction|DogWorkShift|PetSkillLesson|VeterinaryVisit $receipt): string
    {
        return match (true) {
            $receipt instanceof PetCareAction => $receipt->group === 'training' ? 'training' : 'care.'.$receipt->variant,
            $receipt instanceof DogWorkShift => 'work',
            $receipt instanceof PetSkillLesson => 'skill_training',
            $receipt instanceof VeterinaryVisit => 'veterinary.'.$receipt->service->value,
        };
    }

    private function completedAt(PetCareAction|DogWorkShift|PetSkillLesson|VeterinaryVisit $receipt): ?CarbonImmutable
    {
        if (($receipt instanceof PetCareAction || $receipt instanceof DogWorkShift) && $receipt->cancelled_at !== null) {
            return null;
        }

        return match (true) {
            $receipt instanceof PetCareAction, $receipt instanceof DogWorkShift => $receipt->completed_at,
            $receipt instanceof PetSkillLesson => $receipt->trained_at,
            $receipt instanceof VeterinaryVisit => $receipt->performed_at,
        };
    }
}

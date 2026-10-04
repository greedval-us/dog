<?php

namespace App\Modules\Players\Services;

use App\Models\User;
use App\Modules\Players\Calculators\PlayerLevelRules;
use App\Modules\Players\DTO\PlayerProgressFact;
use App\Modules\Players\Enums\AchievementMetric;
use Brick\Math\BigInteger;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Public cross-module operation for completed dog actions and lifetime player progress. */
final class PlayerProgress
{
    public function __construct(private PlayerAchievements $achievements) {}

    public function refreshAchievements(User $user): void
    {
        $this->achievements->synchronize($user);
    }

    /**
     * Call inside the gameplay transaction so the action, reward and counters commit together.
     * Build facts from the locked receipt supplied to the callback, never the caller's instance.
     *
     * @template TReceipt of Model
     *
     * @param  TReceipt  $receipt
     * @param  Closure(TReceipt): PlayerProgressFact  $facts
     */
    public function award(User $user, Model $receipt, Closure $facts, bool $onlyAffectedAchievements = false): int
    {
        if (! $receipt->exists) {
            throw new InvalidArgumentException('Player progress requires a saved action receipt.');
        }

        return DB::transaction(function () use ($user, $receipt, $facts, $onlyAffectedAchievements): int {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);
            $completed = $receipt->newQuery()->whereKey($receipt->getKey())->lockForUpdate()->firstOrFail();

            if ($completed->getAttribute('user_id') !== $owner->id) {
                throw new InvalidArgumentException('Player progress requires a completed action owned by the player.');
            }
            $fact = $facts($completed);
            if ($fact->completedAt === null) {
                throw new InvalidArgumentException('Player progress requires a completed action owned by the player.');
            }

            if (! array_key_exists('experience_awarded', $completed->getAttributes())) {
                throw new InvalidArgumentException('Player progress requires a durable experience receipt marker.');
            }
            $awarded = $completed->getAttribute('experience_awarded');
            if ($awarded !== null) {
                return $awarded;
            }

            $code = $fact->code;
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
                'walks_count' => $owner->walks_count + (int) $fact->walk,
                'trainings_count' => $owner->trainings_count + (int) $fact->training,
                'competition_wins' => $owner->competition_wins + (int) $fact->competitionWin,
                'exhibition_wins' => $owner->exhibition_wins + (int) $fact->exhibitionWin,
                'active_days' => $owner->active_days + (int) ($owner->last_pet_action_at?->setTimezone($dayTimezone)->toDateString() !== $awardedAt->setTimezone($dayTimezone)->toDateString()),
                'last_pet_action_at' => $awardedAt,
            ])->save();

            $completed->forceFill(['experience_awarded' => $experience])->save();
            $receipt->setAttribute('experience_awarded', $experience);
            $this->achievements->synchronize($owner, $onlyAffectedAchievements ? $this->affectedMetrics($fact) : null);

            return $experience;
        }, attempts: 3);
    }

    /** @return list<AchievementMetric> */
    private function affectedMetrics(PlayerProgressFact $fact): array
    {
        $metrics = [AchievementMetric::FirstDog, AchievementMetric::ActiveDays];
        $codeMetrics = match ($fact->code) {
            'care.meal' => [AchievementMetric::Meals, AchievementMetric::DailyMeals],
            'care.wash' => [AchievementMetric::Washes],
            'skill_training' => [AchievementMetric::Skills],
            'work' => [AchievementMetric::Jobs],
            'competition' => [AchievementMetric::CompetitionWins],
            'exhibition' => [AchievementMetric::ExhibitionWins],
            default => str_starts_with($fact->code, 'veterinary.') ? [AchievementMetric::Veterinary] : [],
        };
        if ($fact->walk) {
            $metrics[] = AchievementMetric::Walks;
        }
        if ($fact->training) {
            $metrics[] = AchievementMetric::Trainings;
        }
        if ($fact->competitionWin && ! in_array(AchievementMetric::CompetitionWins, $codeMetrics, true)) {
            $metrics[] = AchievementMetric::CompetitionWins;
        }
        if ($fact->exhibitionWin && ! in_array(AchievementMetric::ExhibitionWins, $codeMetrics, true)) {
            $metrics[] = AchievementMetric::ExhibitionWins;
        }

        return [...$metrics, ...$codeMetrics];
    }
}

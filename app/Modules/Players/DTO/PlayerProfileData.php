<?php

namespace App\Modules\Players\DTO;

use App\Models\User;
use App\Modules\Players\Calculators\PlayerLevelRules;
use Illuminate\Contracts\Support\Arrayable;

/**
 * @phpstan-type Progress array{levelExperience: string, requiredExperience: string, remainingExperience: string, percent: float, nextLevel: int}
 * @phpstan-type Statistics array{actionsCount: int, feedingCount: int, wateringCount: int, playCount: int, groomingCount: int, restCount: int, skillLessonsCount: int, workCount: int, veterinaryCount: int, activeDays: int, lastActionAt: string|null, competitionStarts: int, competitionPodiums: int, competitionWins: int, exhibitionStarts: int, exhibitionPodiums: int, exhibitionWins: int, agilityWins: int, noseworkWins: int, canicrossWins: int, conformationWins: int, progenyStarts: int, progenyWins: int, weeklyEventWins: int, monthlyEventWins: int, titlesCount: int, titledDogsCount: int, eventPrizeCoins: int, eventFeesCoins: int, littersStarted: int, littersBorn: int, puppiesBorn: int, puppiesKept: int, puppiesPurchased: int, puppiesSold: int, puppySalesCoins: int, titledOffspring: int, ammunitionPurchases: int}
 *
 * @implements Arrayable<string, int|string|null|Progress|Statistics>
 */
final readonly class PlayerProfileData implements Arrayable
{
    /**
     * @param  Progress  $progress
     * @param  Statistics  $statistics
     */
    public function __construct(
        public string $name,
        public string $username,
        public ?string $avatarVersion,
        public ?string $bio,
        public int $level,
        public string $experience,
        public int $dogsCount,
        public int $exhibitionWins,
        public int $competitionWins,
        public int $walksCount,
        public int $trainingsCount,
        public ?string $joinedAt,
        public array $progress,
        public array $statistics,
    ) {}

    public static function fromModel(User $user, int $dogsCount, ?PlayerGameStatisticsData $gameStatistics = null): self
    {
        $levelProgress = PlayerLevelRules::progress($user->experience);
        $statistics = $user->pet_statistics;
        $gameStatistics ??= new PlayerGameStatisticsData(competitionWins: $user->competition_wins, exhibitionWins: $user->exhibition_wins);

        return new self(
            name: $user->name,
            username: $user->username,
            avatarVersion: $user->avatarVersion(),
            bio: $user->bio,
            level: $levelProgress['level'],
            experience: $user->experience,
            dogsCount: $dogsCount,
            exhibitionWins: $gameStatistics->exhibitionWins,
            competitionWins: $gameStatistics->competitionWins,
            walksCount: $user->walks_count,
            trainingsCount: $user->trainings_count,
            joinedAt: $user->created_at?->toDateString(),
            progress: [
                'levelExperience' => $levelProgress['levelExperience'],
                'requiredExperience' => $levelProgress['requiredExperience'],
                'remainingExperience' => $levelProgress['remainingExperience'],
                'percent' => $levelProgress['percent'],
                'nextLevel' => $levelProgress['nextLevel'],
            ],
            statistics: [
                'actionsCount' => array_sum($statistics),
                'feedingCount' => $statistics['care.meal'] ?? 0,
                'wateringCount' => $statistics['care.water'] ?? 0,
                'playCount' => ($statistics['care.attention'] ?? 0) + ($statistics['care.toy'] ?? 0),
                'groomingCount' => ($statistics['care.wash'] ?? 0) + ($statistics['care.care'] ?? 0),
                'restCount' => ($statistics['care.nap'] ?? 0) + ($statistics['care.sleep'] ?? 0),
                'skillLessonsCount' => $statistics['skill_training'] ?? 0,
                'workCount' => $statistics['work'] ?? 0,
                'veterinaryCount' => array_sum(array_filter($statistics, static fn (string $code): bool => str_starts_with($code, 'veterinary.'), ARRAY_FILTER_USE_KEY)),
                'activeDays' => $user->active_days,
                'lastActionAt' => $user->last_pet_action_at?->toISOString(),
                ...$gameStatistics->toArray(),
            ],
        );
    }

    /** @return array{name: string, username: string, avatarVersion: string|null, bio: string|null, level: int, experience: string, dogsCount: int, exhibitionWins: int, competitionWins: int, walksCount: int, trainingsCount: int, joinedAt: string|null, progress: Progress, statistics: Statistics} */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'username' => $this->username,
            'avatarVersion' => $this->avatarVersion,
            'bio' => $this->bio,
            'level' => $this->level,
            'experience' => $this->experience,
            'dogsCount' => $this->dogsCount,
            'exhibitionWins' => $this->exhibitionWins,
            'competitionWins' => $this->competitionWins,
            'walksCount' => $this->walksCount,
            'trainingsCount' => $this->trainingsCount,
            'joinedAt' => $this->joinedAt,
            'progress' => $this->progress,
            'statistics' => $this->statistics,
        ];
    }
}

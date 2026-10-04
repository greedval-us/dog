<?php

namespace App\Modules\Players\DTO;

/** @phpstan-type GameStatistics array{competitionStarts: int, competitionPodiums: int, competitionWins: int, exhibitionStarts: int, exhibitionPodiums: int, exhibitionWins: int, agilityWins: int, noseworkWins: int, canicrossWins: int, conformationWins: int, progenyStarts: int, progenyWins: int, weeklyEventWins: int, monthlyEventWins: int, titlesCount: int, titledDogsCount: int, eventPrizeCoins: int, eventFeesCoins: int, littersStarted: int, littersBorn: int, puppiesBorn: int, puppiesKept: int, puppiesPurchased: int, puppiesSold: int, puppySalesCoins: int, titledOffspring: int, ammunitionPurchases: int} */
final readonly class PlayerGameStatisticsData
{
    public function __construct(
        public int $competitionStarts = 0,
        public int $competitionPodiums = 0,
        public int $competitionWins = 0,
        public int $exhibitionStarts = 0,
        public int $exhibitionPodiums = 0,
        public int $exhibitionWins = 0,
        public int $agilityWins = 0,
        public int $noseworkWins = 0,
        public int $canicrossWins = 0,
        public int $conformationWins = 0,
        public int $progenyStarts = 0,
        public int $progenyWins = 0,
        public int $weeklyEventWins = 0,
        public int $monthlyEventWins = 0,
        public int $titlesCount = 0,
        public int $titledDogsCount = 0,
        public int $eventPrizeCoins = 0,
        public int $eventFeesCoins = 0,
        public int $littersStarted = 0,
        public int $littersBorn = 0,
        public int $puppiesBorn = 0,
        public int $puppiesKept = 0,
        public int $puppiesPurchased = 0,
        public int $puppiesSold = 0,
        public int $puppySalesCoins = 0,
        public int $titledOffspring = 0,
        public int $ammunitionPurchases = 0,
    ) {}

    /** @return GameStatistics */
    public function toArray(): array
    {
        return [
            'competitionStarts' => $this->competitionStarts,
            'competitionPodiums' => $this->competitionPodiums,
            'competitionWins' => $this->competitionWins,
            'exhibitionStarts' => $this->exhibitionStarts,
            'exhibitionPodiums' => $this->exhibitionPodiums,
            'exhibitionWins' => $this->exhibitionWins,
            'agilityWins' => $this->agilityWins,
            'noseworkWins' => $this->noseworkWins,
            'canicrossWins' => $this->canicrossWins,
            'conformationWins' => $this->conformationWins,
            'progenyStarts' => $this->progenyStarts,
            'progenyWins' => $this->progenyWins,
            'weeklyEventWins' => $this->weeklyEventWins,
            'monthlyEventWins' => $this->monthlyEventWins,
            'titlesCount' => $this->titlesCount,
            'titledDogsCount' => $this->titledDogsCount,
            'eventPrizeCoins' => $this->eventPrizeCoins,
            'eventFeesCoins' => $this->eventFeesCoins,
            'littersStarted' => $this->littersStarted,
            'littersBorn' => $this->littersBorn,
            'puppiesBorn' => $this->puppiesBorn,
            'puppiesKept' => $this->puppiesKept,
            'puppiesPurchased' => $this->puppiesPurchased,
            'puppiesSold' => $this->puppiesSold,
            'puppySalesCoins' => $this->puppySalesCoins,
            'titledOffspring' => $this->titledOffspring,
            'ammunitionPurchases' => $this->ammunitionPurchases,
        ];
    }

    /** @return array<string, int> */
    public function achievementMetrics(): array
    {
        return [
            'competition_starts' => $this->competitionStarts,
            'competition_podiums' => $this->competitionPodiums,
            'competition_wins' => $this->competitionWins,
            'agility_wins' => $this->agilityWins,
            'nosework_wins' => $this->noseworkWins,
            'canicross_wins' => $this->canicrossWins,
            'exhibition_starts' => $this->exhibitionStarts,
            'exhibition_podiums' => $this->exhibitionPodiums,
            'exhibition_wins' => $this->exhibitionWins,
            'weekly_event_wins' => $this->weeklyEventWins,
            'monthly_event_wins' => $this->monthlyEventWins,
            'progeny_starts' => $this->progenyStarts,
            'litters_born' => $this->littersBorn,
            'puppies_born' => $this->puppiesBorn,
            'puppies_kept' => $this->puppiesKept,
            'puppies_sold' => $this->puppiesSold,
            'titled_offspring' => $this->titledOffspring,
            'ammunition_purchases' => $this->ammunitionPurchases,
        ];
    }
}

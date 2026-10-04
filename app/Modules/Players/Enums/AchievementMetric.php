<?php

namespace App\Modules\Players\Enums;

enum AchievementMetric: string
{
    case FirstDog = 'first_dog';
    case ActiveDogs = 'active_dogs';
    case KennelPurchases = 'kennel_purchases';
    case Trainings = 'trainings';
    case Walks = 'walks';
    case Meals = 'meals';
    case Washes = 'washes';
    case Skills = 'skills';
    case Jobs = 'jobs';
    case Veterinary = 'veterinary';
    case ActiveDays = 'active_days';
    case DailyMeals = 'daily_meals';
    case CompetitionWins = 'competition_wins';
    case ExhibitionWins = 'exhibition_wins';
    case CompetitionStarts = 'competition_starts';
    case CompetitionPodiums = 'competition_podiums';
    case AgilityWins = 'agility_wins';
    case NoseworkWins = 'nosework_wins';
    case CanicrossWins = 'canicross_wins';
    case ExhibitionStarts = 'exhibition_starts';
    case ExhibitionPodiums = 'exhibition_podiums';
    case WeeklyEventWins = 'weekly_event_wins';
    case MonthlyEventWins = 'monthly_event_wins';
    case ProgenyStarts = 'progeny_starts';
    case LittersBorn = 'litters_born';
    case PuppiesBorn = 'puppies_born';
    case PuppiesKept = 'puppies_kept';
    case PuppiesSold = 'puppies_sold';
    case TitledOffspring = 'titled_offspring';
    case AmmunitionPurchases = 'ammunition_purchases';

    public function usesGameHistory(): bool
    {
        return match ($this) {
            self::CompetitionStarts, self::CompetitionPodiums, self::CompetitionWins,
            self::AgilityWins, self::NoseworkWins, self::CanicrossWins,
            self::ExhibitionStarts, self::ExhibitionPodiums, self::ExhibitionWins,
            self::WeeklyEventWins, self::MonthlyEventWins, self::ProgenyStarts,
            self::LittersBorn, self::PuppiesBorn, self::PuppiesKept,
            self::PuppiesSold, self::TitledOffspring, self::AmmunitionPurchases => true,
            default => false,
        };
    }
}

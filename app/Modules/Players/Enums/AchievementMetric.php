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
}

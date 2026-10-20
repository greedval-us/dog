<?php

namespace App\Modules\Pets\Enums;

use App\Modules\Pets\Calculators\PetAgeRules;

enum ExhibitionClass: string
{
    public const int MINIMUM_MONTHS = 3;

    case Baby = 'baby';
    case Puppy = 'puppy';
    case Junior = 'junior';
    case Intermediate = 'intermediate';
    case Open = 'open';
    case Working = 'working';
    case Champion = 'champion';
    case ClubChampion = 'club_champion';
    case Veteran = 'veteran';

    public function includes(int $months): bool
    {
        [$minimum, $maximum] = match ($this) {
            self::Baby => [self::MINIMUM_MONTHS, 6],
            self::Puppy => [6, 9],
            self::Junior => [9, 18],
            self::Intermediate => [15, 24],
            self::Open, self::Working, self::Champion, self::ClubChampion => [15, PHP_INT_MAX],
            self::Veteran => [PetAgeRules::SENIOR_AGE_MONTHS, PHP_INT_MAX],
        };

        return $months >= $minimum && $months < $maximum;
    }

    public function competesForBreedTitles(): bool
    {
        return in_array($this, [self::Intermediate, self::Open, self::Working, self::Champion, self::ClubChampion, self::Veteran], true);
    }

    /** @return list<self> */
    public static function availableAt(int $months): array
    {
        return array_values(array_filter(self::cases(), fn (self $class): bool => ! in_array($class, [self::Working, self::Champion, self::ClubChampion], true) && $class->includes($months)));
    }
}

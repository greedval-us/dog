<?php

namespace App\Modules\Pets\DTO;

use App\Models\Pet;
use App\Modules\Pets\Calculators\PetAgeRules;
use Carbon\CarbonImmutable;

/** @phpstan-type Age array{months: int, years: int, remainingMonths: int, stage: string} */
final readonly class PetAgeData
{
    /** @return Age */
    public static function fromPet(Pet $pet, ?CarbonImmutable $at = null): array
    {
        $at ??= CarbonImmutable::now();
        $at = $pet->archivedAt()?->min($at) ?? $at;
        $months = $pet->ageMonthsAt($at);

        return [
            'months' => $months,
            'years' => intdiv($months, PetAgeRules::MONTHS_PER_YEAR),
            'remainingMonths' => $months % PetAgeRules::MONTHS_PER_YEAR,
            'stage' => match (true) {
                $pet->retired_at !== null => 'retired',
                $months >= PetAgeRules::SENIOR_AGE_MONTHS => 'senior',
                $months >= PetAgeRules::SPORT_MINIMUM_MONTHS => 'adult',
                $months >= 9 => 'junior',
                default => 'puppy',
            },
        ];
    }
}

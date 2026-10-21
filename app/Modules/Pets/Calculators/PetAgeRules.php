<?php

namespace App\Modules\Pets\Calculators;

use DateTimeInterface;

final class PetAgeRules
{
    public const int SECONDS_PER_MONTH = 86400;

    public const int MONTHS_PER_YEAR = 12;

    public const int SPORT_MINIMUM_MONTHS = 18;

    public const int BREEDING_MINIMUM_MONTHS = 18;

    public const int SENIOR_AGE_MONTHS = 96;

    public const int BREEDING_MAXIMUM_MONTHS = 120;

    public const int DEFAULT_LIFESPAN_MONTHS = 168;

    public const int MINIMUM_LIFESPAN_MONTHS = 144;

    public const int MAXIMUM_LIFESPAN_MONTHS = 192;

    public const int MAXIMUM_AGING_PERCENT = 50;

    public function monthsAt(DateTimeInterface $birth, DateTimeInterface $at): int
    {
        return max(0, intdiv($at->getTimestamp() - $birth->getTimestamp(), self::SECONDS_PER_MONTH));
    }

    /** @param array{lifespans: array<string, array{int, int}>, breeds: array<string, array{int, int}>} $balance */
    public function lifespanMonths(string $identity, string $size, string $breed, array $balance): int
    {
        [$minimum, $maximum] = $balance['breeds'][$breed] ?? $balance['lifespans'][$size] ?? [self::DEFAULT_LIFESPAN_MONTHS, self::DEFAULT_LIFESPAN_MONTHS];
        $minimum = max(self::MINIMUM_LIFESPAN_MONTHS, min(self::MAXIMUM_LIFESPAN_MONTHS, $minimum));
        $maximum = max($minimum, min(self::MAXIMUM_LIFESPAN_MONTHS, $maximum));

        return $minimum + (int) (hexdec(substr(hash('sha256', $identity), 0, 8)) % ($maximum - $minimum + 1));
    }

    /** @return array<string, int> */
    public function agingModifiers(int $months, int $lifespan): array
    {
        if ($months <= self::SENIOR_AGE_MONTHS) {
            return [];
        }
        $progress = min(1, ($months - self::SENIOR_AGE_MONTHS) / max(1, $lifespan - self::SENIOR_AGE_MONTHS));
        $percent = (int) round(self::MAXIMUM_AGING_PERCENT * $progress);

        return [
            'energy_recovery_percent' => -$percent,
            'endurance_decay_percent' => $percent,
            'speed_decay_percent' => $percent,
            'strength_decay_percent' => $percent,
            'agility_decay_percent' => $percent,
        ];
    }
}

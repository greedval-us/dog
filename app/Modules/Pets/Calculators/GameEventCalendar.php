<?php

namespace App\Modules\Pets\Calculators;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

final class GameEventCalendar
{
    /** @param array<string,mixed> $balance */
    public function __construct(private array $balance, private string $timezone) {}

    /** @return array{monthIndex:int,showYear:int,showEdition:string} */
    public function editionAt(DateTimeInterface $at): array
    {
        $timezone = new DateTimeZone($this->timezone);
        $date = DateTimeImmutable::createFromInterface($at)->setTimezone($timezone)->setTime(0, 0);
        $epoch = (new DateTimeImmutable($this->balance['epoch'], $timezone))->setTime(0, 0);
        $monthIndex = max(0, (int) $epoch->diff($date)->format('%r%a'));
        $monthsPerYear = (int) $this->balance['months_per_year'];
        $year = (int) $this->balance['first_year'] + intdiv($monthIndex, $monthsPerYear);

        return ['monthIndex' => $monthIndex, 'showYear' => $year, 'showEdition' => sprintf('%04d-%02d', $year, $monthIndex % $monthsPerYear + 1)];
    }
}

<?php

namespace App\Modules\Players\DTO;

final readonly class DailyWorkQuote
{
    public function __construct(public string $workedOn, public int $expectedCoins, public int $expectedGems) {}
}

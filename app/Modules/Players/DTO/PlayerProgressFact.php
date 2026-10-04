<?php

namespace App\Modules\Players\DTO;

use Carbon\CarbonImmutable;

final readonly class PlayerProgressFact
{
    public function __construct(
        public string $code,
        public ?CarbonImmutable $completedAt,
        public bool $walk = false,
        public bool $training = false,
        public bool $competitionWin = false,
        public bool $exhibitionWin = false,
    ) {}
}

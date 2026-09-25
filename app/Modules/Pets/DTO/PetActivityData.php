<?php

namespace App\Modules\Pets\DTO;

use App\Modules\Pets\Enums\PetActivity;
use Carbon\CarbonImmutable;

final readonly class PetActivityData
{
    public function __construct(
        public string $token,
        public PetActivity $activity,
        public CarbonImmutable $startedAt,
        public CarbonImmutable $endsAt,
    ) {}
}

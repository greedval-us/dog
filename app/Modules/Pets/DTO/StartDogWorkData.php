<?php

namespace App\Modules\Pets\DTO;

final readonly class StartDogWorkData
{
    public function __construct(public int $offerId, public string $token) {}
}

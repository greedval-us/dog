<?php

namespace App\Modules\Kennel\DTO;

final readonly class AdoptStarterPetData
{
    public function __construct(public int $dogId, public string $name) {}
}

<?php

namespace App\Data;

final readonly class AdoptStarterPetData
{
    public function __construct(public int $dogId, public string $name) {}
}

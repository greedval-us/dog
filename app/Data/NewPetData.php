<?php

namespace App\Data;

use App\Enums\PetSex;

final readonly class NewPetData
{
    public function __construct(
        public string $name,
        public PetSex $sex,
        public string $coatColor,
        public ?string $description = null,
    ) {}
}

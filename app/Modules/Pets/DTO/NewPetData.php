<?php

namespace App\Modules\Pets\DTO;

use App\Modules\Pets\Enums\PetSex;

final readonly class NewPetData
{
    /** @param array<string, int> $potentials */
    public function __construct(
        public string $name,
        public PetSex $sex,
        public string $coatColor,
        public ?string $description = null,
        public array $potentials = [],
    ) {}
}

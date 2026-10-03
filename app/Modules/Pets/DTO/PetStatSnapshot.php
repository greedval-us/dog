<?php

namespace App\Modules\Pets\DTO;

final readonly class PetStatSnapshot
{
    /**
     * @param  array<string, int>  $values
     * @param  array<string, int>  $potentials
     */
    public function __construct(public array $values, public array $potentials) {}
}

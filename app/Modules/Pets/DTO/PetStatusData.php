<?php

namespace App\Modules\Pets\DTO;

use App\Modules\Pets\Calculators\PetStatusRules;

/** @phpstan-import-type Effect from PetStatusRules */
final readonly class PetStatusData
{
    /**
     * @param  list<Effect>  $buffs
     * @param  list<Effect>  $debuffs
     * @param  array<string, int>  $modifiers
     */
    public function __construct(
        public array $buffs,
        public array $debuffs,
        public array $modifiers,
    ) {}
}

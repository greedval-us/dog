<?php

namespace App\Modules\Pets\Enums;

enum PetStat: string
{
    case Endurance = 'endurance';
    case Speed = 'speed';
    case Strength = 'strength';
    case Agility = 'agility';
    case Obedience = 'obedience';
    case Intelligence = 'intelligence';

    public function potentialColumn(): string
    {
        return $this->value.'_potential';
    }
}

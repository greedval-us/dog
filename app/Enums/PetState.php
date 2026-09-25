<?php

namespace App\Enums;

enum PetState: string
{
    case Health = 'health';
    case Energy = 'energy';
    case Satiety = 'satiety';
    case Hydration = 'hydration';
    case Mood = 'mood';
    case Cleanliness = 'cleanliness';
    case Bond = 'bond';

    public function maximumColumn(): string
    {
        return $this->value.'_max';
    }
}

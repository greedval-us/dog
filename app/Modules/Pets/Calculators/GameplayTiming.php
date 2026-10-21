<?php

namespace App\Modules\Pets\Calculators;

final class GameplayTiming
{
    public const MIN_SECONDS = 5;

    public const MAX_SECONDS = 15;

    public const CARE_DURATION_SECONDS = [
        'meal' => 5, 'water' => 5, 'walk' => 15, 'home' => 10,
        'attention' => 10, 'toy' => 15, 'wash' => 5, 'care' => 10,
        'nap' => 10, 'sleep' => 15,
    ];

    public const CARE_COOLDOWN_SECONDS = [
        'meal' => 10, 'water' => 10, 'walk' => 15, 'home' => 15,
        'attention' => 10, 'toy' => 10, 'wash' => 15, 'care' => 15,
        'nap' => 10, 'sleep' => 15,
    ];

    public const TRAINING_DURATION_SECONDS = ['sprint' => 10, 'agility' => 15, 'precision' => 5, 'stamina' => 15];

    public const TRAINING_COOLDOWN_SECONDS = ['sprint' => 10, 'agility' => 15, 'precision' => 10, 'stamina' => 15];

    public const DEFAULT_TRAINING_DURATION_SECONDS = 10;

    public const DEFAULT_TRAINING_COOLDOWN_SECONDS = 10;

    public const SKILL_COOLDOWN_SECONDS = 10;

    public const DOG_WORK_DURATION_SECONDS = 15;

    public const BREEDING_BIRTH_SECONDS = 15;

    public const BREEDING_COOLDOWN_SECONDS = 15;
}

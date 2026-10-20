<?php

use App\Modules\Pets\Calculators\GameplayTiming;

return [
    // Effects are percentage points of the dog's own capacity; energy costs are units.
    'feeding_by_size' => ['small' => 30, 'medium' => 22, 'large' => 15],

    'minimum_needs' => [
        'walk' => ['satiety' => 10, 'hydration' => 10],
        'play' => ['satiety' => 10, 'hydration' => 10],
    ],

    'quality_bonuses' => [
        'toys' => ['state' => 'mood', 'per_level' => 1, 'base_quality' => 1, 'max_quality' => 10],
        'care' => ['state' => 'cleanliness', 'per_level' => 1, 'base_quality' => 1, 'max_quality' => 10],
    ],

    // Duration and cooldown are seconds. Cooldown begins when the activity ends.
    // Items map inventory categories to uses spent at the start (positive integers).
    // A meal's satiety effect comes from feeding_by_size above.
    'options' => [
        'meal' => [
            'duration' => GameplayTiming::CARE_DURATION_SECONDS['meal'], 'cooldown' => GameplayTiming::CARE_COOLDOWN_SECONDS['meal'], 'energy' => 0,
            'items' => ['food' => 1], 'effects' => [],
        ],
        'water' => [
            'duration' => GameplayTiming::CARE_DURATION_SECONDS['water'], 'cooldown' => GameplayTiming::CARE_COOLDOWN_SECONDS['water'], 'energy' => 0,
            'items' => [], 'effects' => ['hydration' => 35],
        ],
        'walk' => [
            'duration' => GameplayTiming::CARE_DURATION_SECONDS['walk'], 'cooldown' => GameplayTiming::CARE_COOLDOWN_SECONDS['walk'], 'energy' => 12,
            'items' => ['collars' => 1, 'leashes' => 1],
            'effects' => ['mood' => 25, 'bond' => 4, 'satiety' => -8, 'hydration' => -10, 'cleanliness' => -12],
        ],
        'home' => [
            'duration' => GameplayTiming::CARE_DURATION_SECONDS['home'], 'cooldown' => GameplayTiming::CARE_COOLDOWN_SECONDS['home'], 'energy' => 4,
            'items' => [], 'effects' => ['mood' => 8, 'bond' => 1, 'satiety' => -3, 'hydration' => -3],
        ],
        'attention' => [
            'duration' => GameplayTiming::CARE_DURATION_SECONDS['attention'], 'cooldown' => GameplayTiming::CARE_COOLDOWN_SECONDS['attention'], 'energy' => 6,
            'items' => [], 'effects' => ['mood' => 10, 'bond' => 2, 'satiety' => -3, 'hydration' => -3],
        ],
        'toy' => [
            'duration' => GameplayTiming::CARE_DURATION_SECONDS['toy'], 'cooldown' => GameplayTiming::CARE_COOLDOWN_SECONDS['toy'], 'energy' => 10,
            'items' => ['toys' => 1], 'effects' => ['mood' => 18, 'bond' => 4, 'satiety' => -5, 'hydration' => -6],
        ],
        'wash' => [
            'duration' => GameplayTiming::CARE_DURATION_SECONDS['wash'], 'cooldown' => GameplayTiming::CARE_COOLDOWN_SECONDS['wash'], 'energy' => 0,
            'items' => [], 'effects' => ['cleanliness' => 10, 'bond' => 1],
        ],
        'care' => [
            'duration' => GameplayTiming::CARE_DURATION_SECONDS['care'], 'cooldown' => GameplayTiming::CARE_COOLDOWN_SECONDS['care'], 'energy' => 0,
            'items' => ['care' => 1], 'effects' => ['cleanliness' => 25, 'bond' => 3],
        ],
        'nap' => [
            'duration' => GameplayTiming::CARE_DURATION_SECONDS['nap'], 'cooldown' => GameplayTiming::CARE_COOLDOWN_SECONDS['nap'], 'energy' => 0,
            'items' => [], 'effects' => ['energy' => 25, 'health' => 1, 'satiety' => -4, 'hydration' => -4],
        ],
        'sleep' => [
            'duration' => GameplayTiming::CARE_DURATION_SECONDS['sleep'], 'cooldown' => GameplayTiming::CARE_COOLDOWN_SECONDS['sleep'], 'energy' => 0,
            'items' => [], 'effects' => ['energy' => 70, 'health' => 4, 'satiety' => -12, 'hydration' => -12],
        ],
    ],
];

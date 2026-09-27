<?php

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
            'duration' => 30, 'cooldown' => 300, 'energy' => 0,
            'items' => ['food' => 1], 'effects' => [],
        ],
        'water' => [
            'duration' => 15, 'cooldown' => 300, 'energy' => 0,
            'items' => [], 'effects' => ['hydration' => 35],
        ],
        'walk' => [
            'duration' => 300, 'cooldown' => 900, 'energy' => 12,
            'items' => ['collars' => 1, 'leashes' => 1],
            'effects' => ['mood' => 25, 'bond' => 4, 'satiety' => -8, 'hydration' => -10, 'cleanliness' => -12],
        ],
        'home' => [
            'duration' => 120, 'cooldown' => 900, 'energy' => 4,
            'items' => [], 'effects' => ['mood' => 8, 'bond' => 1, 'satiety' => -3, 'hydration' => -3],
        ],
        'attention' => [
            'duration' => 120, 'cooldown' => 600, 'energy' => 6,
            'items' => [], 'effects' => ['mood' => 10, 'bond' => 2, 'satiety' => -3, 'hydration' => -3],
        ],
        'toy' => [
            'duration' => 180, 'cooldown' => 600, 'energy' => 10,
            'items' => ['toys' => 1], 'effects' => ['mood' => 18, 'bond' => 4, 'satiety' => -5, 'hydration' => -6],
        ],
        'wash' => [
            'duration' => 60, 'cooldown' => 1200, 'energy' => 0,
            'items' => [], 'effects' => ['cleanliness' => 10, 'bond' => 1],
        ],
        'care' => [
            'duration' => 180, 'cooldown' => 1200, 'energy' => 0,
            'items' => ['care' => 1], 'effects' => ['cleanliness' => 25, 'bond' => 3],
        ],
        'nap' => [
            'duration' => 300, 'cooldown' => 1800, 'energy' => 0,
            'items' => [], 'effects' => ['energy' => 25, 'satiety' => -4, 'hydration' => -4],
        ],
        'sleep' => [
            'duration' => 1200, 'cooldown' => 3600, 'energy' => 0,
            'items' => [], 'effects' => ['energy' => 70, 'satiety' => -12, 'hydration' => -12],
        ],
    ],
];

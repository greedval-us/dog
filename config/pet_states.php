<?php

return [
    // Rates are percentage points of the pet's own maximum per real hour.
    'decay_per_hour' => [
        'satiety' => 5,
        'hydration' => 5,
        'mood' => 2,
        'cleanliness' => 2,
        'bond' => 0.5,
    ],
    'health_needs' => ['satiety', 'hydration'],
    'health_threshold' => 20,
    'health_loss_per_hour' => 5,
    'energy_threshold' => 50,
    'energy_needs' => ['satiety', 'hydration', 'mood', 'cleanliness', 'health'],
    'energy_recovery_per_hour' => 5,
];

<?php

return [
    'inactivity_seconds' => 3600,
    'cheap_hourly_price' => 1,
    'premium_price_multiplier' => 3,
    'cheap_stat_decay_multiplier' => 2,
    'premium_stat_decay_per_hour' => 0.03,
    'state_decay_per_hour' => [
        'cheap' => ['satiety' => 0, 'hydration' => 0, 'bond' => 1, 'mood' => 0.5],
        'premium' => ['satiety' => 0, 'hydration' => 0, 'bond' => 0.5, 'mood' => 0],
    ],
];

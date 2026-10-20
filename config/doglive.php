<?php

use App\Modules\Pets\Calculators\GameplayTiming;

return [
    'work_timezone' => 'Europe/Moscow',
    'disease_timezone' => 'Europe/Moscow',
    'dog_work_daily_offers' => 6,
    'kennel_price' => 500,
    'breeding_minimum_level' => 5,
    'breeding_cooldown_seconds' => GameplayTiming::BREEDING_COOLDOWN_SECONDS,
    'breeding_birth_seconds' => GameplayTiming::BREEDING_BIRTH_SECONDS,
    'puppy_decision_days' => 7,
    'puppy_http_batch_size' => 20,
    'breeding_rare_colors' => ['liver', 'blue', 'cream'],
    'illustrated_breeds' => ['german_shepherd', 'pit_bull', 'dachshund'],
    'avatar' => [
        'max_kilobytes' => 2048,
        'max_dimension' => 2048,
        'stored_dimension' => 512,
    ],
];

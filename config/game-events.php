<?php

return [
    'timezone' => 'Europe/Moscow',
    'field_size' => 8,
    'daily_limit' => 3,
    'duration_minutes' => 10,
    'closing_minutes' => 15,
    'processing' => [
        'http_batch_size' => 0,
        'background_batch_size' => 100,
    ],
    'frequencies' => [
        'daily' => ['fee' => 25, 'prizes' => [100, 60, 40]],
        'weekly' => ['fee' => 100, 'prizes' => [500, 300, 150]],
        'monthly' => ['fee' => 300, 'prizes' => [2000, 1200, 600]],
    ],
    'disciplines' => [
        'agility' => ['daily_time' => '12:00', 'stages' => ['approach', 'technical', 'finish'], 'energy_cost' => 20],
        'nosework' => ['daily_time' => '18:00', 'stages' => ['containers', 'interior', 'exterior'], 'energy_cost' => 15],
        'canicross' => ['daily_time' => '21:00', 'stages' => ['climb', 'turns', 'finish'], 'energy_cost' => 30],
        'conformation' => ['daily_time' => '18:00', 'stages' => ['inspection', 'stance', 'movement'], 'energy_cost' => 10],
        'progeny' => ['daily_time' => '21:00', 'stages' => ['type', 'uniformity', 'achievements'], 'energy_cost' => 0],
    ],
];

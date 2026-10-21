<?php

return [
    'timezone' => 'Europe/Moscow',
    'field_size' => 8,
    'daily_limit' => 3,
    'pet_daily_limit' => 2,
    'pet_rest_hours' => 2,
    'duration_minutes' => 10,
    'closing_minutes' => 15,
    'progeny_ring' => ['delay_minutes' => 10, 'energy_cost' => 5, 'disciplines' => ['progeny', 'couple', 'breeders']],
    'career' => ['experience_cap' => 1000000, 'eliminated_experience' => 2, 'win_experience' => 10, 'finish_experience' => 5],
    'special_calendar' => [
        'epoch' => '2026-01-01', 'first_year' => 2026, 'months_per_year' => 12,
        'days_ahead' => 7, 'breed_time' => '10:00', 'breed_interval_minutes' => 1,
        'reserved_times' => ['20:30'],
        'events' => [
            ['specialShow' => 'eurasia', 'showLeg' => 'president', 'time' => '09:00'],
            ['specialShow' => 'eurasia', 'showLeg' => 'cis', 'time' => '12:00'],
            ['specialShow' => 'russia', 'showLeg' => 'rkf_cup', 'time' => '15:00'],
            ['specialShow' => 'russia', 'showLeg' => 'moscow_cup', 'time' => '18:30'],
            ['specialShow' => 'mazover', 'showLeg' => '', 'time' => '21:00'],
        ],
    ],
    'regulation_calendar' => [
        'odor_test_lead_hours' => 3,
        'specialty_delay_hours' => 3,
        'maximum_course_seconds' => 360,
        'international_countries' => ['DE', 'FR', 'FI'],
        'regions' => ['moscow', 'saint_petersburg', 'novosibirsk'],
        'judges' => [
            ['id' => 'virtual-judge-1', 'name' => 'Virtual judge 1'],
            ['id' => 'virtual-judge-2', 'name' => 'Virtual judge 2'],
            ['id' => 'virtual-judge-3', 'name' => 'Virtual judge 3'],
            ['id' => 'virtual-judge-4', 'name' => 'Virtual judge 4'],
            ['id' => 'virtual-judge-5', 'name' => 'Virtual judge 5'],
            ['id' => 'virtual-judge-6', 'name' => 'Virtual judge 6'],
            ['id' => 'virtual-judge-7', 'name' => 'Virtual judge 7'],
        ],
        'disciplines' => [
            'conformation' => [
                'daily' => [
                    ['rank' => 'cac_cf'], ['rank' => 'cac_rkf'], ['rank' => 'cac_rkf_special'],
                    ['rank' => 'cacib', 'system' => 'fci', 'country' => 'DE'], ['rank' => 'cacib', 'system' => 'fci', 'country' => 'FR'], ['rank' => 'cacib', 'system' => 'fci', 'country' => 'FI'],
                    ['rank' => 'specialty'],
                ],
                'weekly' => [['rank' => 'cac_rkf_special'], ['rank' => 'specialty']],
                'monthly' => [['rank' => 'cacib', 'system' => 'fci']],
            ],
            'progeny' => ['daily' => [['rank' => 'qualifying']], 'weekly' => [['rank' => 'qualifying']], 'monthly' => [['rank' => 'qualifying']]],
            'agility' => [
                'daily' => [['rank' => 'qualifying'], ['rank' => 'qualifying', 'format' => 'jumping'], ['rank' => 'certificate', 'system' => 'fci'], ['rank' => 'certificate']],
                'weekly' => [['rank' => 'certificate'], ['rank' => 'certificate', 'sportScope' => 'breed']], 'monthly' => [['rank' => 'championship'], ['rank' => 'cup'], ['rank' => 'cac_rkf']],
            ],
            'nosework' => ['daily' => [['rank' => 'qualifying'], ['rank' => 'certificate']], 'weekly' => [['rank' => 'certificate'], ['rank' => 'certificate', 'sportScope' => 'breed']], 'monthly' => [['rank' => 'championship'], ['rank' => 'cup'], ['rank' => 'cac_rkf']]],
            'canicross' => ['daily' => [['rank' => 'qualifying'], ['rank' => 'certificate']], 'weekly' => [['rank' => 'certificate'], ['rank' => 'certificate', 'sportScope' => 'breed']], 'monthly' => [['rank' => 'championship'], ['rank' => 'cup'], ['rank' => 'cac_rkf']]],
        ],
    ],
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

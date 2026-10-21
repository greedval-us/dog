<?php

return [
    'german_shepherd' => [
        'recognized_rkf' => true, 'recognized_fci' => true, 'fci_group' => 1, 'working_breed' => true, 'club_system' => 'nkp',
        'height_ranges' => ['male' => [60, 65], 'female' => [55, 60]],
    ],
    'dachshund' => [
        'recognized_rkf' => true, 'recognized_fci' => true, 'fci_group' => 4, 'working_breed' => true, 'club_system' => 'nkp',
        'height_ranges' => ['male' => [18, 25], 'female' => [18, 25]],
    ],
    'pit_bull' => [
        'recognized_rkf' => false, 'recognized_fci' => false, 'fci_group' => null, 'working_breed' => false, 'club_system' => 'none',
        'height_ranges' => ['male' => [43, 53], 'female' => [40, 50]],
    ],
];

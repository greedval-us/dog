<?php

return [
    'version' => 2,
    'source_keys' => ['rkf_sport_titles'],
    'certificate_codes' => ['agility' => 'CACAg', 'nosework' => 'CACNW', 'canicross' => 'CACT'],
    'minimum_participants' => [
        'general' => [
            'agility' => ['championship' => 15, 'cup' => 15, 'cac_rkf' => 10, 'cac_rkf_special' => 10, 'certificate' => 6, 'international' => 15],
            'nosework' => ['championship' => 3, 'cup' => 3, 'cac_rkf' => 3, 'cac_rkf_special' => 3, 'certificate' => 3],
            'canicross' => ['championship' => 5, 'cup' => 4, 'cac_rkf' => 3, 'cac_rkf_special' => 3, 'certificate' => 3, 'international' => 3],
        ],
        'breed' => [
            'agility' => ['certificate' => 3, 'cac_rkf' => 5, 'cac_rkf_special' => 5],
            'nosework' => ['certificate' => 3, 'cac_rkf' => 4, 'cac_rkf_special' => 4],
            'canicross' => ['certificate' => 3, 'cac_rkf' => 3, 'cac_rkf_special' => 3],
        ],
    ],
    'race_qualification_factors' => ['1' => 1.3, '2' => 1.5, '2-RUS' => 1.5, '3' => 1.3, '4' => 1.3],
    'nosework_minimum_category_score' => 80,
    'nosework_strict_first_place' => true,
];

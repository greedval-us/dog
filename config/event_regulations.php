<?php

return [
    'version' => 2,
    'sources' => [
        'rkf_shows_2026' => 'https://help.rkf.online/ru/knowledge_base/art/2428/cat/489/polozenie-o-sertifikatnih-vistavkah-rkf-vstupaet-v-silu-s-01012026',
        'fci_shows_2026' => 'https://www.fci.be/medias/EXP-REG-en-20260101-22363.pdf',
        'rkf_championships' => 'https://help.rkf.online/ru/knowledge_base/art/2633/cat/489/polozenie-o-titulah-rkf',
        'fci_championships' => 'https://www.fci.be/medias/FCI-REG-TIT-en-19872.pdf',
        'rkf_agility_2026' => 'https://help.rkf.online/ru/knowledge_base/art/1414/cat/518/vremennie-pravila-provedenija-meroprijatij-po-discipline-adziliti-dejstvujut-s-01052023-po-31122026',
        'fci_agility' => 'https://www.fci.be/medias/AGI-REG-en-23672.pdf',
        'fci_agility_champion' => 'https://www.fci.be/medias/AGI-REG-CIAG-en-23688.pdf',
        'rkf_nosework_2026_09' => 'https://help.rkf.online/ru/knowledge_base/download/2941/file/de4e30d5843be1958b4bc768465dcc2ecc5759a4',
        'rkf_racing_2026_06' => 'https://help.rkf.online/ru/knowledge_base/preview/2827/file/21da0cd0a594a13c5c813afa40d2893e24bca151',
        'rkf_sport_titles' => 'https://help.rkf.online/ru/knowledge_base/download/2830/file/ee70115539ab0eeb256589bf7f0977314d2c23b0',
        'rkf_sport_events' => 'https://help.rkf.online/ru/knowledge_base/preview/2829/file/6bc65b99981eb7f30f67ad3df3e9781f7133ea22',
    ],
    'regulations' => [
        'agility' => ['national_months' => 18, 'international_months' => 24, 'height_boundaries' => ['S' => 35, 'M' => 43, 'I' => 48], 'fault_points' => 5, 'excellent_max_faults' => 5.99, 'very_good_max_faults' => 15.99, 'good_max_faults' => 25.99],
        'nosework' => [
            'minimum_months' => ['nw_0' => 6, 'nw_1' => 8, 'nw_2' => 12, 'nw_3' => 12, 'nw_v' => 96, 'nw_test' => 6],
            'odors' => ['pr1' => ['birch', 'lavender', 'anise'], 'pr2' => ['cinnamon', 'orange', 'clove']],
            'required_odors' => ['nw_0' => 1, 'nw_1' => 2, 'nw_2' => 3, 'nw_3' => 3, 'nw_v' => 3],
            'category_max_score' => 100, 'category_qualifying_score' => 80, 'false_alert_penalty' => 15, 'missed_hide_penalty' => 15,
            'regular_categories' => ['interior', 'exterior', 'objects', 'transport'],
            'certificate_categories' => ['interior', 'exterior', 'objects', 'transport'],
            'advancement_events' => 2, 'qualification_categories' => 3,
        ],
        'canicross' => ['minimum_months' => 15, 'qualification_factors' => ['1' => 1.3, '2-RUS' => 1.5, '3' => 1.3, '4' => 1.3]],
        'shows' => ['placements' => 4, 'final_placements' => 3, 'progeny_minimum' => 3, 'progeny_maximum' => 5],
        'certificate_validity_months' => 60,
        'championship_interval_months' => 12,
    ],
    // Scores and probability thresholds model virtual judging; RKF/FCI do not prescribe these numerical exterior cutoffs.
    'simulation' => [
        'show_grades' => ['excellent' => 85, 'very_good' => 75, 'good' => 60, 'satisfactory' => 40],
        'young_show_grades' => ['very_promising' => 85, 'promising' => 65],
        'exterior_weight' => 0.8, 'presentation_weight' => 0.2,
        'base_error_probability' => 0.42, 'quality_error_reduction' => 0.003, 'focus_error_reduction' => 0.001,
        'fatigue_error_increase' => 0.002, 'minimum_error_probability' => 0.02, 'maximum_error_probability' => 0.8,
        'base_stage_seconds' => 100, 'quality_time_reduction' => 0.55, 'fault_time_seconds' => 5,
        'stage_difficulty' => [0.04, 0.12, 0.08, 0.1, 0.12],
        'tactics' => [
            'careful' => ['risk' => -0.14, 'pace' => 0.9, 'fatigue' => 8, 'focus' => 7],
            'balanced' => ['risk' => 0.0, 'pace' => 1.0, 'fatigue' => 13, 'focus' => -2],
            'bold' => ['risk' => 0.12, 'pace' => 1.15, 'fatigue' => 22, 'focus' => -9],
        ],
    ],
];

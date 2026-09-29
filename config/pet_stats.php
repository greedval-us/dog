<?php

return [
    // Absolute stat points lost per real hour; zero disables decay for that stat.
    'decay_per_hour' => [
        'endurance' => 0.05,
        'speed' => 0.05,
        'strength' => 0.05,
        'agility' => 0.05,
        'obedience' => 0.025,
        'intelligence' => 0.025,
    ],
    'minimum' => 1,
];

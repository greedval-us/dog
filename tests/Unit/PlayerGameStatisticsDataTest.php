<?php

use App\Modules\Players\DTO\PlayerGameStatisticsData;

test('collective statistics survive their public array round trip without adding new achievement metrics', function () {
    $statistics = new PlayerGameStatisticsData(coupleWins: 2, breedersWins: 3, exhibitionWins: 5);

    $restored = new PlayerGameStatisticsData(...$statistics->toArray());

    expect($restored->coupleWins)->toBe(2);
    expect($restored->breedersWins)->toBe(3);
    expect($restored->toArray()['coupleWins'])->toBe(2);
    expect($restored->toArray()['breedersWins'])->toBe(3);
    expect($restored->achievementMetrics()['exhibition_wins'])->toBe(5);
    expect($restored->achievementMetrics())->not->toHaveKey('couple_wins');
    expect($restored->achievementMetrics())->not->toHaveKey('breeders_wins');
});

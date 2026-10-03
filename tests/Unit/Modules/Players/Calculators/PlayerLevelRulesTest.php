<?php

use App\Modules\Players\Calculators\PlayerLevelRules;

test('level transitions double the required experience and retain progress towards the next level', function (
    string $totalExperience,
    int $level,
    string $levelExperience,
    string $requiredExperience,
    string $remainingExperience,
    float $percent,
) {
    $progress = PlayerLevelRules::progress($totalExperience);

    expect($progress)->toMatchArray([
        'level' => $level,
        'levelExperience' => $levelExperience,
        'requiredExperience' => $requiredExperience,
        'remainingExperience' => $remainingExperience,
        'percent' => $percent,
        'nextLevel' => $level + 1,
    ]);
})->with([
    'new player' => ['0', 1, '0', '100', '100', 0.0],
    'one experience before level two' => ['99', 1, '99', '100', '1', 99.0],
    'exact level two' => ['100', 2, '0', '200', '200', 0.0],
    'one experience before level three' => ['299', 2, '199', '200', '1', 99.5],
    'exact level three' => ['300', 3, '0', '400', '400', 0.0],
    'one experience before level four' => ['699', 3, '399', '400', '1', 99.75],
    'exact level four' => ['700', 4, '0', '800', '800', 0.0],
    'experience carries into level four' => ['750', 4, '50', '800', '750', 6.25],
    'enough experience for several levels' => ['4700', 6, '1600', '3200', '1600', 50.0],
    'fractional percentages truncate to two decimal places' => ['6500', 7, '200', '6400', '6200', 3.12],
]);

test('experience stays exact beyond browser and database integer limits without a level cap', function (
    string $totalExperience,
    int $level,
    string $levelExperience,
    string $requiredExperience,
    string $remainingExperience,
) {
    $progress = PlayerLevelRules::progress($totalExperience);

    expect($progress)->toMatchArray([
        'level' => $level,
        'levelExperience' => $levelExperience,
        'requiredExperience' => $requiredExperience,
        'remainingExperience' => $remainingExperience,
        'nextLevel' => $level + 1,
    ]);
    expect($progress['percent'])->toBeFloat()->toBeBetween(0.0, 100.0);
})->with([
    'beyond JavaScript safe integer' => ['9007199254740993', 47, '1970324836974693', '7036874417766400', '5066549580791707'],
    'beyond signed sixty four bit integer' => ['9223372036854775808', 57, '2017612633061982308', '7205759403792793600', '5188146770730811292'],
    'one experience before level one hundred and one' => ['126765060022822940149670320537499', 100, '63382530011411470074835160268799', '63382530011411470074835160268800', '1'],
    'exact level one hundred and one' => ['126765060022822940149670320537500', 101, '0', '126765060022822940149670320537600', '126765060022822940149670320537600'],
    'carry after level one hundred and one' => ['126765060022822940149670320537623', 101, '123', '126765060022822940149670320537600', '126765060022822940149670320537477'],
    'halfway through level one hundred' => ['95073795017117205112252740403100', 100, '31691265005705735037417580134400', '63382530011411470074835160268800', '31691265005705735037417580134400'],
]);

test('the progress percentage remains finite when total experience exceeds the float range', function () {
    $totalExperience = '1'.str_repeat('0', 400);

    $progress = PlayerLevelRules::progress($totalExperience);

    expect($progress)->toMatchArray(['level' => 1323, 'nextLevel' => 1324, 'percent' => 9.23]);
});

test('negative and malformed experience values are rejected', function (string $totalExperience) {
    expect(fn (): array => PlayerLevelRules::progress($totalExperience))->toThrow(InvalidArgumentException::class);
})->with([
    'negative' => ['-1'],
    'empty' => [''],
    'decimal' => ['100.0'],
    'scientific notation' => ['1e2'],
    'explicit plus sign' => ['+100'],
    'leading whitespace' => [' 100'],
    'trailing whitespace' => ['100 '],
    'trailing newline' => ["100\n"],
    'leading zero' => ['0100'],
    'multiple zeroes' => ['00'],
    'nonnumeric' => ['hungry'],
]);

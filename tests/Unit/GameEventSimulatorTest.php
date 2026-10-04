<?php

use App\Modules\Pets\Calculators\GameEventSimulator;

test('ranking uses discipline rules before the time tie breaker', function (string $discipline, int $expected) {
    $fewerPenalties = ['eliminated' => false, 'penalties' => 0, 'score' => 20.0, 'time' => 250.0];
    $higherScore = ['eliminated' => false, 'penalties' => 1, 'score' => 90.0, 'time' => 120.0];

    expect((new GameEventSimulator)->compareResults($discipline, $fewerPenalties, $higherScore))->toBe($expected);
})->with([
    'agility' => ['agility', -1],
    'nosework' => ['nosework', -1],
    'canicross' => ['canicross', 1],
    'conformation' => ['conformation', 1],
    'progeny' => ['progeny', 1],
]);

test('ranking places eliminated entries last and resolves equal scores by time', function () {
    $simulator = new GameEventSimulator;
    $finished = ['eliminated' => false, 'penalties' => 3, 'score' => 20.0, 'time' => 250.0];
    $eliminated = ['eliminated' => true, 'penalties' => 0, 'score' => 90.0, 'time' => 120.0];
    expect($simulator->compareResults('conformation', $finished, $eliminated))->toBe(-1);
    $faster = [...$finished, 'time' => 200.0];
    expect($simulator->compareResults('conformation', $finished, $faster))->toBe(1);
    expect($simulator->compareResults('conformation', $finished, $finished))->toBe(0);
});

function eventSimulationSnapshot(): array
{
    return [
        'stats' => array_fill_keys(['endurance', 'speed', 'strength', 'agility', 'obedience', 'intelligence'], 60),
        'states' => ['energy' => 100, 'health' => 100, 'cleanliness' => 100, 'bond' => 80, 'mood' => 80],
        'modifiers' => [], 'career_experience' => 0, 'skills' => [], 'exterior' => ['type' => 80, 'structure' => 80, 'movement' => 80],
    ];
}

test('tactical choices conserve fatigue and alter subsequent stages with reproducible replay', function () {
    $simulator = new GameEventSimulator;
    $snapshot = eventSimulationSnapshot();
    $rules = ['version' => 1, 'stages' => ['climb', 'turns', 'finish']];
    $careful = ['stages' => ['careful', 'careful', 'careful']];
    $bold = ['stages' => ['bold', 'bold', 'bold']];
    $draws = array_fill(0, 6, 0.9);

    $conserved = $simulator->simulate('canicross', $snapshot, $careful, $rules, $draws);
    $rushed = $simulator->simulate('canicross', $snapshot, $bold, $rules, $draws);

    expect($conserved['stages'][2]['fatigue'])->toBe(24.0);
    expect($rushed['stages'][2]['fatigue'])->toBe(66.0);
    expect($rushed['stages'][2]['focus'])->toBeLessThan($conserved['stages'][2]['focus']);
    expect($simulator->simulate('canicross', $snapshot, $bold, $rules, $draws))->toBe($rushed);
    expect($simulator->simulate('canicross', $snapshot, ['stages' => ['careful', 'bold', 'bold']], $rules, $draws)['stages'][1])->not->toBe($rushed['stages'][1]);
});

test('careful handling prevents mistakes where a bold approach incurs penalties', function () {
    $simulator = new GameEventSimulator;
    $rules = ['version' => 1, 'stages' => ['approach', 'technical', 'finish']];
    $draws = array_fill(0, 6, 0.2);

    $careful = $simulator->simulate('agility', eventSimulationSnapshot(), ['stages' => ['careful', 'careful', 'careful']], $rules, $draws);
    $bold = $simulator->simulate('agility', eventSimulationSnapshot(), ['stages' => ['bold', 'bold', 'bold']], $rules, $draws);

    expect($careful['penalties'])->toBe(0);
    expect($bold['penalties'])->toBeGreaterThan(0);
    expect($careful['eliminated'])->toBeFalse();
});

test('ordinary conformation judging ignores titles while a progeny achievement phase rewards descendants', function () {
    $simulator = new GameEventSimulator;
    $snapshot = eventSimulationSnapshot();
    $rules = ['version' => 1, 'stages' => ['inspection', 'stance', 'movement']];
    $plan = ['stages' => ['balanced', 'balanced', 'balanced']];
    $draws = array_fill(0, 6, 0.9);
    $plain = $simulator->simulate('conformation', $snapshot, $plan, $rules, $draws);
    $snapshot['titles'] = array_fill(0, 10, ['code' => 'daily_winner']);

    expect($simulator->simulate('conformation', $snapshot, $plan, $rules, $draws))->toBe($plain);

    $snapshot['offspring'] = array_fill(0, 3, ['exterior' => ['type' => 80], 'titles' => []]);
    $without = $simulator->simulate('progeny', $snapshot, $plan, $rules, $draws);
    $snapshot['offspring'][0]['titles'] = [['code' => 'conformation_daily_winner']];
    $with = $simulator->simulate('progeny', $snapshot, $plan, $rules, $draws);
    expect($with['stages'][2]['score'])->toBeGreaterThan($without['stages'][2]['score']);
    expect($with['stages'][0]['score'])->toBe($without['stages'][0]['score']);
});

test('career consistency is bounded even after thousands of wins', function () {
    $simulator = new GameEventSimulator;
    $snapshot = eventSimulationSnapshot();
    $rules = ['version' => 1, 'stages' => ['approach', 'technical', 'finish']];
    $plan = ['stages' => ['balanced', 'balanced', 'balanced']];
    $draws = array_fill(0, 6, 0.1);
    $snapshot['career_experience'] = 80;
    $experienced = $simulator->simulate('agility', $snapshot, $plan, $rules, $draws);
    $snapshot['career_experience'] = 1000000;

    expect($simulator->simulate('agility', $snapshot, $plan, $rules, $draws))->toBe($experienced);
});

test('a false scent indication eliminates a search while missing a hide only incurs a penalty', function () {
    $simulator = new GameEventSimulator;
    $snapshot = eventSimulationSnapshot();
    $rules = ['version' => 1, 'stages' => ['containers', 'interior', 'exterior']];
    $plan = ['stages' => ['balanced', 'balanced', 'balanced']];

    $false = $simulator->simulate('nosework', $snapshot, $plan, $rules, [0.0, 0.9, 0.9, 0.9, 0.9, 0.9]);
    $miss = $simulator->simulate('nosework', $snapshot, $plan, $rules, [0.9, 0.0, 0.9, 0.9, 0.9, 0.9]);

    expect($false['eliminated'])->toBeTrue();
    expect($false['stages'][0]['note'])->toBe('false_alert');
    expect($miss['eliminated'])->toBeFalse();
    expect($miss['stages'][0]['note'])->toBe('missed_hide');
    expect($miss['penalties'])->toBe(1);
});

test('canicross climbing rewards strength while technical agility rewards obedience', function () {
    $simulator = new GameEventSimulator;
    $snapshot = eventSimulationSnapshot();
    $rules = ['version' => 1, 'stages' => ['climb', 'turns', 'finish']];
    $plan = ['stages' => ['balanced', 'balanced', 'balanced']];
    $draws = array_fill(0, 6, 0.9);
    $baseline = $simulator->simulate('canicross', $snapshot, $plan, $rules, $draws);
    $snapshot['stats']['strength'] = 500;
    $strong = $simulator->simulate('canicross', $snapshot, $plan, $rules, $draws);

    expect($strong['stages'][0]['time'])->toBeLessThan($baseline['stages'][0]['time']);
    expect($strong['stages'][1]['time'])->toBe($baseline['stages'][1]['time']);
    $snapshot = eventSimulationSnapshot();
    $rules['stages'] = ['approach', 'technical', 'finish'];
    $baseline = $simulator->simulate('agility', $snapshot, $plan, $rules, $draws);
    $snapshot['stats']['obedience'] = 500;
    $obedient = $simulator->simulate('agility', $snapshot, $plan, $rules, $draws);
    expect($obedient['stages'][1]['time'])->toBeLessThan($baseline['stages'][1]['time']);
});

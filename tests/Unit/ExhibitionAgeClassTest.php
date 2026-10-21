<?php

use App\Modules\Pets\Calculators\GameEventRandomness;
use App\Modules\Pets\Enums\ExhibitionClass;
use App\Modules\Pets\Generators\GameEventNpcGenerator;

test('exhibition classes preserve overlapping windows and exact age boundaries', function (int $months, array $classes) {
    expect(array_map(fn (ExhibitionClass $class): string => $class->value, ExhibitionClass::availableAt($months)))->toBe($classes);
})->with([
    'too young' => [2, []],
    'baby opens' => [3, ['baby']],
    'puppy replaces baby' => [6, ['puppy']],
    'junior replaces puppy' => [9, ['junior']],
    'three choices' => [15, ['junior', 'intermediate', 'open']],
    'junior closes' => [18, ['intermediate', 'open']],
    'intermediate closes' => [24, ['open']],
    'veteran opens' => [96, ['open', 'veteran']],
    'last eligible month' => [119, ['open', 'veteran']],
    'voluntary career continues' => [120, ['open', 'veteran']],
]);

test('adult show club dogs use equal preparation and retain the age class and sex of their ring', function () {
    $generator = new GameEventNpcGenerator(new GameEventRandomness);
    $stats = [];
    foreach (['intermediate' => 18, 'open' => 30, 'veteran' => 100] as $class => $age) {
        $snapshot = $generator->generate([
            'breed' => 'beagle', 'breed_id' => 7, 'size' => 'medium',
            'exhibition_class' => $class, 'age_months' => $age, 'sex' => 'female',
        ], $class.':breed-7:female', 'same-judging-reference', 0);
        expect($snapshot)->toMatchArray(['sex' => 'female', 'age_months' => $age, 'exhibition_class' => $class]);
        $stats[] = $snapshot['stats'];
    }

    expect($stats[0])->toBe($stats[1])->toBe($stats[2]);
});

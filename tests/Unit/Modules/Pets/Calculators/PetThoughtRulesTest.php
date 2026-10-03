<?php

use App\Modules\Pets\Calculators\PetThoughtRules;

test('need conditions respect strict and inclusive threshold boundaries', function (string $operator, float $satiety, bool $expected) {
    $conditions = [['field' => 'satiety', 'operator' => $operator, 'value' => 25]];

    expect((new PetThoughtRules)->matches($conditions, ['satiety' => $satiety]))->toBe($expected);
})->with([
    'lt below' => ['lt', 24.99, true],
    'lt exactly' => ['lt', 25.0, false],
    'lt above' => ['lt', 25.01, false],
    'lte below' => ['lte', 24.99, true],
    'lte exactly' => ['lte', 25.0, true],
    'lte above' => ['lte', 25.01, false],
    'gt below' => ['gt', 24.99, false],
    'gt exactly' => ['gt', 25.0, false],
    'gt above' => ['gt', 25.01, true],
    'gte below' => ['gte', 24.99, false],
    'gte exactly' => ['gte', 25.0, true],
    'gte above' => ['gte', 25.01, true],
    'eq below' => ['eq', 24.99, false],
    'eq exactly' => ['eq', 25.0, true],
    'eq above' => ['eq', 25.01, false],
    'neq below' => ['neq', 24.99, true],
    'neq exactly' => ['neq', 25.0, false],
    'neq above' => ['neq', 25.01, true],
]);

test('numeric catalogue strings compare with numeric pet needs', function (string $operator, string $threshold, bool $expected) {
    $conditions = [['field' => 'hydration', 'operator' => $operator, 'value' => $threshold]];

    expect((new PetThoughtRules)->matches($conditions, ['hydration' => 25.0]))->toBe($expected);
})->with([
    'equal integer string' => ['eq', '25', true],
    'equal decimal string' => ['eq', '25.00', true],
    'below decimal threshold' => ['lt', '25.01', true],
    'different numeric string' => ['neq', '25', false],
]);

test('disease conditions normalize zero and one from catalogue JSON', function (bool $hasDisease, int|string $value, bool $expected) {
    $conditions = [['field' => 'has_disease', 'operator' => 'eq', 'value' => $value]];

    expect((new PetThoughtRules)->matches($conditions, ['has_disease' => $hasDisease]))->toBe($expected);
})->with([
    'healthy with zero' => [false, 0, true],
    'sick with zero' => [true, 0, false],
    'sick with one' => [true, 1, true],
    'healthy with one' => [false, 1, false],
    'healthy with string zero' => [false, '0', true],
    'sick with string one' => [true, '1', true],
]);

test('unknown or malformed catalogue conditions never trigger a thought', function (array $conditions) {
    expect((new PetThoughtRules)->matches($conditions, ['satiety' => 25.0, 'has_disease' => true]))->toBeFalse();
})->with([
    'unknown field' => [[['field' => 'unknown_need', 'operator' => 'lt', 'value' => 30]]],
    'unknown operator' => [[['field' => 'satiety', 'operator' => 'between', 'value' => 30]]],
    'scalar JSON condition' => [['hungry']],
    'null JSON condition' => [[null]],
    'missing field' => [[['operator' => 'lt', 'value' => 30]]],
    'missing operator' => [[['field' => 'satiety', 'value' => 30]]],
    'missing value' => [[['field' => 'satiety', 'operator' => 'lt']]],
    'numeric field name' => [[['field' => 1, 'operator' => 'lt', 'value' => 30]]],
    'numeric operator' => [[['field' => 'satiety', 'operator' => 1, 'value' => 30]]],
    'null value' => [[['field' => 'satiety', 'operator' => 'lt', 'value' => null]]],
    'array JSON value' => [[['field' => 'satiety', 'operator' => 'lt', 'value' => [30]]]],
    'object JSON value' => [[['field' => 'satiety', 'operator' => 'lt', 'value' => (object) ['threshold' => 30]]]],
    'nonnumeric need threshold' => [[['field' => 'satiety', 'operator' => 'lt', 'value' => 'hungry']]],
    'invalid disease value' => [[['field' => 'has_disease', 'operator' => 'eq', 'value' => 'sick']]],
]);

test('a thought requires every configured condition to match', function (string $activity, bool $expected) {
    $conditions = [
        ['field' => 'satiety', 'operator' => 'lt', 'value' => 30],
        ['field' => 'activity', 'operator' => 'eq', 'value' => 'idle'],
    ];

    expect((new PetThoughtRules)->matches($conditions, ['satiety' => 25.0, 'activity' => $activity]))->toBe($expected);
})->with(['hungry and resting' => ['idle', true], 'hungry but working' => ['work', false]]);

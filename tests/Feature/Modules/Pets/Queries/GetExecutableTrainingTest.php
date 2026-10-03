<?php

use App\Models\Pet;
use App\Models\StatusEffect;
use App\Models\Training;
use App\Modules\Pets\Queries\GetExecutableTraining;
use App\Modules\Pets\Queries\GetTrainingOptions;
use App\Modules\Pets\Services\PetTrainingPreparation;

test('an executable training keeps the same costs gains and risk snapshot as its catalogue option', function () {
    $injury = StatusEffect::factory()->create(['kind' => 'debuff']);
    $training = Training::factory()->for($injury, 'statusEffect')->create(['risk_chance' => 12500]);
    $variant = 'training:'.$training->id;

    $option = app(GetExecutableTraining::class)->handle($variant);
    $catalogue = app(GetTrainingOptions::class)->handle('en');

    expect($option)->not->toBeNull();
    expect($option->toArray())->toBe($catalogue[$variant]->toArray())->toMatchArray([
        'group' => 'training', 'label' => 'Running', 'duration' => 120, 'cooldown' => 600, 'energy' => 15,
        'requirements' => ['sports'], 'optional' => [], 'uses' => ['sports' => 1],
        'effects' => ['satiety' => -5, 'hydration' => -8, 'cleanliness' => -3],
        'statGains' => ['speed' => 4, 'endurance' => 4], 'trainingName' => ['ru' => 'Бег', 'en' => 'Running'],
        'risks' => [['effect' => $injury->snapshot(), 'chance' => 10000, 'item_name' => $training->name, 'quality' => 0]],
    ]);
});

test('training preparation loads only the selected executable training', function () {
    $pet = Pet::factory()->make();
    $training = Training::factory()->create();
    Training::factory()->count(2)->create();
    $retrieved = [];
    $events = Training::getEventDispatcher();
    Training::setEventDispatcher(clone $events);
    Training::retrieved(function (Training $loaded) use (&$retrieved): void {
        $retrieved[] = $loaded->id;
    });

    try {
        $option = app(PetTrainingPreparation::class)->option($pet, 'training:'.$training->id);

        expect($option->trainingName)->toBe(['ru' => 'Бег', 'en' => 'Running']);
        expect($retrieved)->toBe([$training->id]);
    } finally {
        Training::setEventDispatcher($events);
    }
});

test('inactive and invalid trainings are absent from executable lookup and the catalogue', function (array $attributes) {
    $training = Training::factory()->create($attributes);
    $variant = 'training:'.$training->id;

    $option = app(GetExecutableTraining::class)->handle($variant);
    $catalogue = app(GetTrainingOptions::class)->handle('en');

    expect($option)->toBeNull();
    expect($catalogue)->not->toHaveKey($variant);
})->with([
    'inactive' => [['is_active' => false]],
    'invalid gains' => [['stat_gains' => ['coins' => 1]]],
]);

test('the executable lookup rejects malformed variants instead of treating them as a training id', function (string $template) {
    $training = Training::factory()->create();
    $variant = str_replace('{id}', (string) $training->id, $template);

    expect(app(GetExecutableTraining::class)->handle($variant))->toBeNull();
})->with([
    'missing id' => ['training:'],
    'zero id' => ['training:0'],
    'leading zero' => ['training:0{id}'],
    'negative id' => ['training:-{id}'],
    'decimal id' => ['training:{id}.0'],
    'trailing text' => ['training:{id}extra'],
    'trailing newline' => ["training:{id}\n"],
    'overflowing id' => ['training:9223372036854775808'],
    'another care group' => ['walk:{id}'],
]);

test('the executable lookup returns no option for a deleted training', function () {
    $training = Training::factory()->create();
    $variant = 'training:'.$training->id;
    $training->delete();

    expect(app(GetExecutableTraining::class)->handle($variant))->toBeNull();
});

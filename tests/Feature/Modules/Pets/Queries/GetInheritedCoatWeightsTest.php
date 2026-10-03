<?php

use App\Models\CoatInheritanceRule;
use App\Models\Dog;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Queries\GetInheritedCoatWeights;
use Database\Seeders\BreedingCatalogueSeeder;
use Database\Seeders\DogSeeder;
use Illuminate\Support\Facades\DB;

test('darker ancestors raise dark offspring chances while every real coat remains possible', function () {
    $this->seed([DogSeeder::class, BreedingCatalogueSeeder::class]);
    $dog = Dog::query()->where('breed', 'pit_bull')->sole();
    $owner = User::factory()->createOne();
    $father = Pet::factory()->for($dog)->for($owner)->createOne(['coat_color' => 'fawn']);
    $mother = Pet::factory()->female()->for($dog)->for($owner)->createOne(['coat_color' => 'fawn']);
    $query = app(GetInheritedCoatWeights::class);
    $baseline = $query->handle($father, $mother);
    foreach ([$father, $mother] as $parent) {
        $grandfather = Pet::factory()->for($dog)->for($owner)->createOne(['coat_color' => 'black']);
        $grandmother = Pet::factory()->female()->for($dog)->for($owner)->createOne(['coat_color' => 'brindle']);
        $parent->forceFill(['father_id' => $grandfather->id, 'mother_id' => $grandmother->id])->save();
    }

    $inherited = $query->handle($father, $mother);

    expect($inherited['black'])->toBeGreaterThan($baseline['black']);
    expect($inherited['brindle'])->toBeGreaterThan($baseline['brindle']);
    expect($inherited['fawn'])->toBeLessThan($baseline['fawn']);
    expect(array_sum($inherited))->toBe(100000);
    expect($inherited['blue'])->toBeGreaterThan(0);
    expect($query->handle($mother, $father))->toBe($inherited);
});

test('ancestral weights include great grandparents and repeated lineage paths using bounded batched reads', function () {
    $dog = Dog::factory()->createOne([
        'breed' => 'pit_bull',
        'coat_colors' => array_fill_keys(['black', 'brindle', 'fawn', 'blue'], ['ru' => 'Окрас', 'en' => 'Coat']),
    ]);
    $owner = User::factory()->createOne();
    foreach (['black', 'fawn', 'blue'] as $color) {
        CoatInheritanceRule::factory()->for($dog)->createOne([
            'first_color' => 'fawn', 'second_color' => 'fawn', 'offspring_color' => $color, 'weight' => 1,
        ]);
    }
    $greatGrandfather = Pet::factory()->for($dog)->for($owner)->createOne(['coat_color' => 'black']);
    $grandfather = Pet::factory()->for($dog)->for($owner)->createOne(['coat_color' => 'black', 'father_id' => $greatGrandfather->id]);
    $grandmother = Pet::factory()->female()->for($dog)->for($owner)->createOne(['coat_color' => 'fawn', 'father_id' => $greatGrandfather->id]);
    $father = Pet::factory()->for($dog)->for($owner)->createOne([
        'coat_color' => 'fawn', 'father_id' => $grandfather->id, 'mother_id' => $grandmother->id,
    ]);
    $mother = Pet::factory()->female()->for($dog)->for($owner)->createOne(['coat_color' => 'fawn']);
    $father->load('dog');
    DB::enableQueryLog();

    $weights = app(GetInheritedCoatWeights::class)->handle($father, $mother);

    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    expect($weights)->toBe(['black' => 30612, 'blue' => 14286, 'fawn' => 55102]);
    expect(count($queries))->toBeLessThanOrEqual(3);
});

test('missing ancestors and corrupt self cycles do not invent or duplicate inherited colors', function () {
    $this->seed([DogSeeder::class, BreedingCatalogueSeeder::class]);
    $dog = Dog::query()->where('breed', 'german_shepherd')->sole();
    $owner = User::factory()->createOne();
    $father = Pet::factory()->for($dog)->for($owner)->createOne(['coat_color' => 'black']);
    $mother = Pet::factory()->female()->for($dog)->for($owner)->createOne(['coat_color' => 'sable']);
    $query = app(GetInheritedCoatWeights::class);
    $withoutAncestors = $query->handle($father, $mother);
    $father->forceFill(['father_id' => $father->id])->save();

    $weights = $query->handle($father, $mother);

    expect($weights)->toBe($withoutAncestors);
    expect(array_sum($weights))->toBe(100000);
    expect($weights['liver'])->toBeGreaterThan(0);
});

test('unsupported coat distributions cannot create an invented offspring coat', function (string $scenario) {
    $this->seed([DogSeeder::class, BreedingCatalogueSeeder::class]);
    $dog = Dog::query()->where('breed', 'pit_bull')->sole();
    $owner = User::factory()->createOne();
    $father = Pet::factory()->for($dog)->for($owner)->createOne(['coat_color' => 'black']);
    $mother = Pet::factory()->female()->for($dog)->for($owner)->createOne(['coat_color' => 'fawn']);
    $rules = CoatInheritanceRule::query()->where('dog_id', $dog->id)->where('first_color', 'black')->where('second_color', 'fawn');

    if ($scenario === 'missing matrix') {
        $rules->delete();
    } elseif ($scenario === 'unknown parent coat') {
        $father->forceFill(['coat_color' => 'neon'])->save();
        CoatInheritanceRule::factory()->for($dog)->createOne([
            'first_color' => 'fawn', 'second_color' => 'neon', 'offspring_color' => 'black', 'weight' => 10000,
        ]);
    } elseif ($scenario === 'unknown offspring coat') {
        CoatInheritanceRule::factory()->for($dog)->createOne([
            'first_color' => 'black', 'second_color' => 'fawn', 'offspring_color' => 'neon', 'weight' => 1,
        ]);
    } elseif ($scenario === 'different breeds') {
        $mother->forceFill(['dog_id' => Dog::query()->where('breed', 'german_shepherd')->sole()->id])->save();
    } elseif ($scenario === 'nonpositive matrix weight') {
        $rules->where('offspring_color', 'blue')->update(['weight' => 0]);
    }

    expect(app(GetInheritedCoatWeights::class)->handle($father, $mother))->toBe([]);
})->with(['missing matrix', 'unknown parent coat', 'unknown offspring coat', 'different breeds', 'nonpositive matrix weight']);

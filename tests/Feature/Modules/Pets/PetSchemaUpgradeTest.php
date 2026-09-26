<?php

use App\Models\Dog;
use App\Models\Pet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('upgrading existing pets preserves traits activity timestamps and trained values', function () {
    $pet = Pet::factory()->create(['strength' => 17, 'state_updated_at' => '2026-09-24 12:00:00']);
    $tracking = require database_path('migrations/2026_09_25_120209_add_activity_tracking_to_pets_table.php');
    $catalogues = require database_path('migrations/2026_09_25_120210_create_pet_catalogues_and_relationships.php');
    $catalogues->down();
    $tracking->down();
    DB::table('pets')->where('id', $pet->id)->update([
        'traits' => json_encode(['friendly', 'custom_trait', 'friendly']),
        'activity' => 'training', 'activity_started_at' => '2026-09-25 12:00:00',
        'activity_ends_at' => '2026-09-25 13:00:00',
    ]);

    $tracking->up();
    $catalogues->up();

    expect(Schema::hasColumn('pets', 'traits'))->toBeFalse();
    $this->assertDatabaseHas('pets', [
        'id' => $pet->id, 'strength' => 17, 'stats_updated_at' => '2026-09-24 12:00:00',
        'last_activity_at' => '2026-09-25 12:00:00',
        'activity_started_at' => '2026-09-25 12:00:00', 'activity_ends_at' => '2026-09-25 13:00:00',
    ]);
    expect($pet->refresh()->activity_token)->not->toBeNull();
    expect($pet->isBusy())->toBeTrue();
    expect($pet->characterTraits->pluck('code')->all())->toBe(['friendly', 'custom_trait']);
    $this->assertDatabaseCount('character_trait_pet', 2);

    $catalogues->down();
    expect(json_decode(DB::table('pets')->where('id', $pet->id)->value('traits'), true))->toBe(['friendly', 'custom_trait']);
    $catalogues->up();
});

test('invalid legacy traits stop the migration before removing or replacing data', function (string $json) {
    $pet = Pet::factory()->create();
    $catalogues = require database_path('migrations/2026_09_25_120210_create_pet_catalogues_and_relationships.php');
    $catalogues->down();
    DB::table('pets')->where('id', $pet->id)->update(['traits' => $json]);

    expect(fn () => $catalogues->up())->toThrow(RuntimeException::class);

    expect(Schema::hasColumn('pets', 'traits'))->toBeTrue();
    expect(Schema::hasTable('character_traits'))->toBeFalse();
    expect(DB::table('pets')->where('id', $pet->id)->value('traits'))->toBe($json);
})->with(['object' => '{"friendly": true}', 'non-string code' => '[false]']);

test('pets without traits retain an empty collection after migration', function (?string $json) {
    $pet = Pet::factory()->create();
    $catalogues = require database_path('migrations/2026_09_25_120210_create_pet_catalogues_and_relationships.php');
    $catalogues->down();
    DB::table('pets')->where('id', $pet->id)->update(['traits' => $json]);

    $catalogues->up();

    expect($pet->refresh()->characterTraits)->toBeEmpty();
})->with(['sql null' => null, 'json null' => 'null', 'empty array' => '[]']);

test('appearance migration preserves pets with empty legacy photo lists', function (?string $photos) {
    $pet = Pet::factory()->create(['strength' => 17]);
    $indexes = require database_path('migrations/2026_09_26_072820_add_game_lookup_indexes.php');
    $migration = require database_path('migrations/2026_09_25_150513_add_appearance_assets_to_pets.php');
    $indexes->down();
    $migration->down();
    DB::table('pets')->where('id', $pet->id)->update(['photos' => $photos]);

    $migration->up();
    $indexes->up();

    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'strength' => 17]);
    expect(Schema::hasColumn('pets', 'photos'))->toBeFalse();
})->with(['sql null' => null, 'json null' => 'null', 'empty array' => '[]']);

test('appearance migration refuses to discard existing photos', function () {
    $pet = Pet::factory()->create();
    $indexes = require database_path('migrations/2026_09_26_072820_add_game_lookup_indexes.php');
    $migration = require database_path('migrations/2026_09_25_150513_add_appearance_assets_to_pets.php');
    $indexes->down();
    $migration->down();
    DB::table('pets')->where('id', $pet->id)->update(['photos' => '["legacy.png"]']);

    expect(fn () => $migration->up())->toThrow(RuntimeException::class);

    expect(json_decode(DB::table('pets')->where('id', $pet->id)->value('photos'), true))->toBe(['legacy.png']);
});

test('standardizing energy updates existing dogs without refilling spent energy or resetting state clocks', function (int $maximum, float $energy, float $expected) {
    $dog = Dog::factory()->create(['energy_max' => $maximum, 'health_max' => 120]);
    $pet = Pet::factory()->for($dog)->create([
        'energy_max' => $maximum, 'energy' => $energy, 'satiety' => 42,
        'state_updated_at' => '2026-09-25 12:00:00', 'stats_updated_at' => '2026-09-25 11:00:00',
    ]);
    $migration = require database_path('migrations/2026_09_26_080015_standardize_dog_energy_capacity.php');

    $migration->up();

    $this->assertDatabaseHas('dog', ['id' => $dog->id, 'energy_max' => 100, 'health_max' => 120]);
    $this->assertDatabaseHas('pets', [
        'id' => $pet->id, 'energy_max' => 100, 'energy' => $expected, 'satiety' => 42,
        'state_updated_at' => '2026-09-25 12:00:00', 'stats_updated_at' => '2026-09-25 11:00:00',
    ]);
})->with([
    'above the new capacity' => [120, 120.0, 100.0],
    'spent energy' => [110, 72.25, 72.25],
    'exhausted dog' => [120, 0.0, 0.0],
    'previously smaller capacity' => [90, 90.0, 90.0],
    'already standard capacity' => [100, 100.0, 100.0],
    'excess energy with standard capacity' => [100, 120.0, 100.0],
]);

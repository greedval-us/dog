<?php

use App\Models\CharacterTrait;
use App\Models\Disease;
use App\Models\Pet;
use App\Models\PetDisease;
use App\Models\Skill;
use Database\Seeders\CharacterTraitSeeder;
use Illuminate\Database\QueryException;

test('traits are shared catalogue entries and can be attached to many pets', function () {
    $this->seed(CharacterTraitSeeder::class);
    $this->seed(CharacterTraitSeeder::class);
    $first = Pet::factory()->create();
    $second = Pet::factory()->create();
    $friendly = CharacterTrait::query()->where('code', 'friendly')->firstOrFail();
    $active = CharacterTrait::query()->where('code', 'active')->firstOrFail();

    $first->characterTraits()->attach([$friendly->id, $active->id]);
    $second->characterTraits()->attach($friendly);

    $this->assertDatabaseCount('character_traits', 5);
    expect($first->characterTraits->pluck('code')->all())->toBe(['friendly', 'active']);
    expect($second->characterTraits->pluck('code')->all())->toBe(['friendly']);
    expect($friendly->pets()->count())->toBe(2);
    expect($friendly->name)->toBe(['ru' => 'Дружелюбный', 'en' => 'Friendly']);
});

test('each pet stores its own progress for the same skill', function () {
    $first = Pet::factory()->create();
    $second = Pet::factory()->create();
    $skill = Skill::factory()->create();

    $first->skills()->attach($skill, ['level' => 3, 'experience' => 125]);
    $second->skills()->attach($skill);

    $this->assertDatabaseHas('pet_skill', ['pet_id' => $first->id, 'skill_id' => $skill->id, 'level' => 3, 'experience' => 125]);
    $this->assertDatabaseHas('pet_skill', ['pet_id' => $second->id, 'skill_id' => $skill->id, 'level' => 1, 'experience' => 0]);
    expect($first->skills->sole()->is($skill))->toBeTrue();
    expect($skill->pets()->count())->toBe(2);
});

test('duplicate skill and trait assignments are rejected by the database', function (string $relation, string $model) {
    $pet = Pet::factory()->create();
    $entry = $model::factory()->create();
    $pet->{$relation}()->attach($entry);

    expect(fn () => $pet->{$relation}()->attach($entry))->toThrow(QueryException::class);
})->with([
    'skills' => ['skills', Skill::class],
    'traits' => ['characterTraits', CharacterTrait::class],
]);

test('disease episodes preserve recovery history and allow later reinfection', function () {
    $pet = Pet::factory()->create();
    $other = Pet::factory()->create();
    $disease = Disease::factory()->create();
    $past = PetDisease::factory()->for($pet)->for($disease)->recovered()->create();
    $current = PetDisease::factory()->for($pet)->for($disease)->create();
    PetDisease::factory()->for($other)->for($disease)->create();

    expect($pet->diseaseEpisodes()->count())->toBe(2);
    expect($pet->activeDiseaseEpisodes->modelKeys())->toBe([$current->id]);
    expect($current->pet->is($pet))->toBeTrue();
    expect($current->disease->is($disease))->toBeTrue();
    expect($past->ended_at)->not->toBeNull();
    expect($disease->episodes()->count())->toBe(3);
});

test('deleting a pet removes its associations while preserving the catalogues', function () {
    $pet = Pet::factory()->create();
    $trait = CharacterTrait::factory()->create();
    $skill = Skill::factory()->create();
    $episode = PetDisease::factory()->for($pet)->create();
    $pet->characterTraits()->attach($trait);
    $pet->skills()->attach($skill);

    $pet->delete();

    $this->assertDatabaseCount('character_trait_pet', 0);
    $this->assertDatabaseCount('pet_skill', 0);
    $this->assertModelMissing($episode);
    $this->assertModelExists($trait);
    $this->assertModelExists($skill);
    $this->assertModelExists($episode->disease);
});

test('referenced catalogue entries cannot be deleted', function (string $kind) {
    $pet = Pet::factory()->create();

    $entry = match ($kind) {
        'skills' => Skill::factory()->create(),
        'characterTraits' => CharacterTrait::factory()->create(),
        'diseases' => Disease::factory()->create(),
    };

    if ($kind === 'diseases') {
        PetDisease::factory()->for($pet)->for($entry, 'disease')->create();
    } else {
        $pet->{$kind}()->attach($entry);
    }

    expect(fn () => $entry->delete())->toThrow(QueryException::class);
})->with(['skills', 'characterTraits', 'diseases']);

test('removing an owner preserves the pet and its acquired characteristics', function () {
    $pet = Pet::factory()->create();
    $skill = Skill::factory()->create();
    $pet->skills()->attach($skill, ['level' => 2]);
    $episode = PetDisease::factory()->for($pet)->create();

    $pet->user->delete();

    expect($pet->refresh()->user_id)->toBeNull();
    $this->assertDatabaseHas('pet_skill', ['pet_id' => $pet->id, 'skill_id' => $skill->id, 'level' => 2]);
    $this->assertModelExists($episode);
});

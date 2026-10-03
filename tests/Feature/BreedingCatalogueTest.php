<?php

use App\Models\BreedingPartner;
use App\Models\CoatInheritanceRule;
use App\Models\Dog;
use App\Modules\Pets\Enums\PetStat;
use Database\Seeders\BreedingCatalogueSeeder;
use Database\Seeders\DogSeeder;

test('breeding catalogues keep real rare colors and complete canonical weight distributions for each breed', function () {
    $this->seed([DogSeeder::class, BreedingCatalogueSeeder::class]);

    foreach (['german_shepherd' => 'liver', 'pit_bull' => 'blue', 'dachshund' => 'cream'] as $breed => $rareColor) {
        $dog = Dog::query()->where('breed', $breed)->sole();
        expect(array_keys($dog->coat_colors))->toContain($rareColor)->toHaveCount(4);
        $combinations = CoatInheritanceRule::query()->where('dog_id', $dog->id)->get()
            ->groupBy(fn (CoatInheritanceRule $rule): string => $rule->first_color.':'.$rule->second_color);

        expect($combinations)->toHaveCount(10);
        foreach ($combinations as $rules) {
            expect($rules->sum('weight'))->toBe(10000);
            expect($rules->pluck('offspring_color')->all())->toHaveCount(4)->toContain($rareColor);
            foreach ($rules as $rule) {
                expect(strcmp($rule->first_color, $rule->second_color))->toBeLessThanOrEqual(0);
                expect($rule->weight)->toBeGreaterThan(0);
            }
        }
    }
});

test('reseeded breeding partners keep their identities and original moderate training snapshots', function () {
    $this->seed([DogSeeder::class, BreedingCatalogueSeeder::class]);
    $partners = BreedingPartner::query()->with('pet.dog')->get();
    $snapshots = [];
    foreach ($partners as $partner) {
        $snapshots[$partner->id] = $partner->pet->getAttributes();
        expect($partner->pet->user_id)->toBeNull();
        expect($partner->price)->toBe(100);
        foreach (PetStat::cases() as $stat) {
            expect($partner->pet->getAttribute($stat->potentialColumn()))->toBe($partner->pet->dog->getAttribute($stat->potentialColumn()));
            expect($partner->pet->getAttribute($stat->value))->toBe((int) round($partner->pet->getAttribute($stat->potentialColumn()) * 0.7));
        }
    }
    Dog::query()->where('breed', 'german_shepherd')->update(['endurance_potential' => 999]);

    $this->seed(BreedingCatalogueSeeder::class);

    $this->assertDatabaseCount('breeding_partners', 6);
    foreach (BreedingPartner::query()->with('pet')->get() as $partner) {
        expect($partner->pet->getAttributes())->toBe($snapshots[$partner->id]);
    }
});

test('refreshing the ordinary breed catalogue preserves breeding color labels', function () {
    $this->seed([DogSeeder::class, BreedingCatalogueSeeder::class]);

    $this->seed(DogSeeder::class);

    foreach (['german_shepherd' => 'liver', 'pit_bull' => 'blue', 'dachshund' => 'cream'] as $breed => $rareColor) {
        expect(Dog::query()->where('breed', $breed)->sole()->coat_colors)->toHaveKey($rareColor);
    }
});

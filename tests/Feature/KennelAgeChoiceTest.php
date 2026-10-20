<?php

use App\Models\Dog;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Kennel\Actions\PurchaseKennelPet;
use App\Modules\Kennel\DTO\PurchaseKennelPetData;
use App\Modules\Kennel\Exceptions\AdoptionUnavailable;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->freezeSecond();
});

test('both starter ages are free and start with fresh care reserves', function (int $months) {
    $dog = Dog::factory()->create(['is_starter' => true]);
    $owner = User::factory()->create(['coins' => 37]);
    $this->actingAs($owner)->post(route('kennel.store'), ['dog_id' => $dog->id, 'name' => 'Рэй', 'age_months' => $months])
        ->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
    $pet = $owner->pets()->sole();
    expect($pet->born_at->equalTo(now()->subDays($months)))->toBeTrue()
        ->and($pet->state_updated_at->equalTo(now()))->toBeTrue()
        ->and($pet->stats_updated_at->equalTo(now()))->toBeTrue()
        ->and($pet->satiety)->toBe((float) $pet->satiety_max)
        ->and($owner->fresh()->coins)->toBe(37);
})->with([3, 18]);

test('the kennel advertises both age choices', function () {
    $this->actingAs(User::factory()->create())->get(route('kennel.index'))->assertInertia(fn (Assert $page) => $page
        ->has('ageOptions', 2)->where('ageOptions.0.months', 3)->where('ageOptions.1.months', 18));
});

test('the starter age must be explicitly chosen from the available ages', function (mixed $age) {
    $dog = Dog::factory()->create(['is_starter' => true]);
    $this->actingAs(User::factory()->create())->post(route('kennel.store'), ['dog_id' => $dog->id, 'name' => 'Рэй', 'age_months' => $age])
        ->assertSessionHasErrors('age_months');
    $this->assertDatabaseCount('pets', 0);
})->with([null, 0, 6, 120, 'forged']);

test('paid dog receipts preserve selected age and reject a changed replay', function () {
    $dog = Dog::factory()->create(['is_starter' => true]);
    $owner = User::factory()->create(['coins' => 2000, 'pet_slots' => 2]);
    Pet::factory()->for($owner)->create();
    $token = (string) Str::uuid();
    $action = app(PurchaseKennelPet::class);
    $data = new PurchaseKennelPetData($dog->id, 'Рэй', 500, $token, 18);
    $receipt = $action->handle($owner, $data);
    $replay = $action->handle($owner, $data);
    expect($replay->id)->toBe($receipt->id)->and($receipt->age_months)->toBe(18)
        ->and(Pet::query()->findOrFail($receipt->pet_id)->born_at->equalTo(now()->subDays(18)))->toBeTrue()
        ->and($owner->fresh()->coins)->toBe(1500);
    expect(fn () => $action->handle($owner, new PurchaseKennelPetData($dog->id, 'Рэй', 500, $token, 3)))
        ->toThrow(AdoptionUnavailable::class);
    $this->assertDatabaseCount('kennel_purchases', 1);
});

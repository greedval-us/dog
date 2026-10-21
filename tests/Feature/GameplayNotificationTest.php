<?php

use App\Models\Achievement;
use App\Models\BreedingLitter;
use App\Models\Pet;
use App\Models\PetCareAction;
use App\Models\Puppy;
use App\Models\User;
use App\Modules\Pets\Actions\PublishPetSale;
use App\Modules\Pets\Actions\PurchasePetSale;
use App\Modules\Pets\Services\PuppyLifecycle;
use App\Modules\Players\DTO\PlayerProgressFact;
use App\Modules\Players\Services\PlayerProgress;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->withoutVite();
    $this->freezeSecond();
});

test('a gameplay achievement unlock reaches the bell once in both languages with an action link', function () {
    $owner = User::factory()->create();
    Achievement::factory()->create([
        'name' => ['ru' => 'Первая тренировка', 'en' => 'First training'],
        'rules' => ['metric' => 'trainings', 'target' => 1],
    ]);
    $pet = Pet::factory()->for($owner)->create();
    $receipt = PetCareAction::factory()->create(['pet_id' => $pet->id, 'user_id' => $owner->id, 'completed_at' => now()]);
    $facts = fn (PetCareAction $completed): PlayerProgressFact => new PlayerProgressFact('training', $completed->completed_at, training: true);

    app(PlayerProgress::class)->award($owner, $receipt, $facts);
    app(PlayerProgress::class)->award($owner, $receipt, $facts);
    app(PlayerProgress::class)->refreshAchievements($owner, notifyOnUnlock: true);

    $notification = $owner->notifications()->sole();
    $this->actingAs($owner)->getJson(route('notifications.index'))
        ->assertJsonCount(1, 'items')
        ->assertJsonPath('items.0.kind', 'achievement')
        ->assertJsonPath('items.0.message.ru', 'Ты получил достижение «Первая тренировка».')
        ->assertJsonPath('items.0.message.en', 'You unlocked the achievement “First training”.')
        ->assertJsonPath('items.0.actionUrl', route('players.achievements', $owner->username, absolute: false))
        ->assertJsonPath('unreadCount', 1);
    $this->patchJson(route('notifications.update', $notification->id))->assertJsonPath('unreadCount', 0);
    $this->getJson(route('notifications.index'))->assertJsonCount(0, 'items');
});

test('viewing historical achievements backfills unlocks without sending notifications', function () {
    $viewer = User::factory()->create();
    $owner = User::factory()->create(['trainings_count' => 10]);
    $achievement = Achievement::factory()->create(['rules' => ['metric' => 'trainings', 'target' => 1]]);

    $this->actingAs($viewer)->get(route('players.achievements', $owner->username))->assertOk();
    app(PlayerProgress::class)->refreshAchievements($owner, notifyOnUnlock: true);

    $this->assertDatabaseHas('player_achievements', ['user_id' => $owner->id, 'achievement_id' => $achievement->id, 'progress' => 1]);
    $this->assertDatabaseCount('notifications', 0);
});

test('actual birth notifies each active puppy recipient once and skips blocked recipients', function () {
    $initiator = User::factory()->create();
    $studOwner = User::factory()->create();
    $blocked = User::factory()->create(['status' => 'blocked']);
    $parent = Pet::factory()->for($initiator)->create();
    $litter = BreedingLitter::factory()->create(['own_pet_id' => $parent->id, 'born_at' => now()->addSeconds(15)]);
    Puppy::factory()->for($litter, 'litter')->for($initiator)->count(2)->create();
    Puppy::factory()->for($litter, 'litter')->for($studOwner)->create();
    Puppy::factory()->for($litter, 'litter')->for($blocked)->create();
    $lifecycle = app(PuppyLifecycle::class);
    $lifecycle->synchronize();
    $this->assertDatabaseCount('notifications', 0);
    $this->travelTo($litter->born_at);

    $lifecycle->synchronize();
    $lifecycle->synchronize();

    $notification = $initiator->notifications()->sole();
    expect($notification->data)->toMatchArray([
        'kind' => 'breeding_success',
        'message' => ['ru' => 'Разведение прошло успешно. Тебя ждут щенки: 2.', 'en' => 'Your breeding was successful. Puppies ready for you: 2.'],
        'actionUrl' => route('puppies.index', absolute: false),
    ]);
    expect($studOwner->notifications()->sole()->data['message']['en'])->toBe('Your breeding was successful. Puppies ready for you: 1.');
    expect($blocked->notifications()->exists())->toBeFalse();
    $this->assertDatabaseCount('notifications', 2);
});

test('a rolled back sale leaves no notification and a later successful retry sends one', function () {
    $seller = User::factory()->create(['coins' => 0]);
    $buyer = User::factory()->create(['coins' => 500]);
    $pet = Pet::factory()->for($seller)->create();
    $listing = app(PublishPetSale::class)->handle($seller, $pet->id, 100);
    $token = (string) Str::uuid();

    expect(fn () => DB::transaction(function () use ($buyer, $listing, $token): void {
        app(PurchasePetSale::class)->handle($buyer, $listing->id, 100, $token);
        throw new RuntimeException('Rollback the completed sale.');
    }))->toThrow(RuntimeException::class, 'Rollback the completed sale.');

    $this->assertDatabaseCount('notifications', 0);
    $this->assertDatabaseCount('pet_sale_purchases', 0);
    app(PurchasePetSale::class)->handle($buyer, $listing->id, 100, $token);
    expect($seller->notifications()->sole()->data['kind'])->toBe('pet_sold');
});

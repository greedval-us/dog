<?php

use App\Models\Achievement;
use App\Models\BreedingLitter;
use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\PlayerAchievement;
use App\Models\Puppy;
use App\Models\ShopOffer;
use App\Models\User;
use App\Modules\Inventory\Actions\PurchaseItem;
use App\Modules\Inventory\DTO\PurchaseItemData;
use App\Modules\Pets\Actions\PurchasePuppy;
use App\Modules\Pets\Enums\PetStat;
use App\Modules\Pets\Services\GameEventProcessor;
use App\Modules\Pets\Services\PuppyLifecycle;
use Database\Seeders\AchievementSeeder;
use Database\Seeders\AmmunitionSeeder;
use Illuminate\Support\Str;

function mechanicAchievement(User $owner, string $code): PlayerAchievement
{
    return PlayerAchievement::query()->where('user_id', $owner->id)
        ->where('achievement_id', Achievement::query()->where('code', $code)->select('id'))->sole();
}

test('birth achievements belong to the litter initiator even when another player receives the puppies', function () {
    $this->freezeSecond();
    $this->seed(AchievementSeeder::class);
    $breeder = User::factory()->create();
    $recipient = User::factory()->create();
    $litter = BreedingLitter::factory()->create(['initiator_id' => $breeder->id, 'born_at' => now()]);
    Puppy::factory()->for($litter, 'litter')->for($recipient)->count(2)->create();

    expect(app(PuppyLifecycle::class)->synchronizeForOwner($recipient))->toBe(2);

    $achievement = mechanicAchievement($breeder, 'first-litter');
    expect($achievement->unlocked_at)->not->toBeNull();
    expect(mechanicAchievement($breeder, 'puppies-10')->progress)->toBe(2);
    $this->assertDatabaseMissing('player_achievements', ['user_id' => $recipient->id]);
    expect(app(PuppyLifecycle::class)->synchronizeForOwner($recipient))->toBe(0);
    expect($achievement->fresh()->unlocked_at)->toEqual($achievement->unlocked_at);
    expect($breeder->fresh()->experience)->toBe('0');
});

test('selling a puppy immediately unlocks the sellers achievement and a retry preserves the award', function () {
    $this->freezeSecond();
    $this->seed(AchievementSeeder::class);
    $seller = User::factory()->create(['coins' => 40]);
    $buyer = User::factory()->create(['coins' => 700]);
    $litter = BreedingLitter::factory()->create(['initiator_id' => $seller->id, 'born_at' => now()->subHour(), 'delivered_at' => now()->subHour()]);
    $puppy = Puppy::factory()->for($litter, 'litter')->for($seller)->create(['status' => 'listed', 'sale_price' => 137]);
    $token = (string) Str::uuid();

    $placement = app(PurchasePuppy::class)->handle($buyer, $puppy->id, 'Рэй', 137, $token);

    $achievement = mechanicAchievement($seller, 'puppy-sold');
    expect($achievement->unlocked_at)->not->toBeNull();
    $this->assertDatabaseMissing('player_achievements', ['user_id' => $buyer->id, 'achievement_id' => $achievement->achievement_id]);
    expect(app(PurchasePuppy::class)->handle($buyer, $puppy->id, 'Рэй', 137, $token)->id)->toBe($placement->id);
    expect($achievement->fresh()->unlocked_at)->toEqual($achievement->unlocked_at);
    expect($seller->fresh()->coins)->toBe(177);
    expect($seller->fresh()->experience)->toBe('0');
    $this->assertDatabaseCount('puppy_placements', 1);
});

test('ammunition purchases unlock preparation achievements using durable receipts without rewarding retries', function () {
    $this->freezeSecond();
    $this->seed([AchievementSeeder::class, AmmunitionSeeder::class]);
    $owner = User::factory()->create(['coins' => 1000]);
    $offers = ShopOffer::query()->orderBy('sort_order')->limit(3)->get();
    $data = [];
    foreach ($offers as $offer) {
        $data[] = new PurchaseItemData($offer->id, $offer->item_id, 'coins', $offer->price, (string) Str::uuid());
    }
    app(PurchaseItem::class)->handle($owner, $data[0]);
    app(PurchaseItem::class)->handle($owner, $data[1]);
    expect(mechanicAchievement($owner, 'ammunition-prepared')->progress)->toBe(2);
    expect(mechanicAchievement($owner, 'ammunition-prepared')->unlocked_at)->toBeNull();

    $purchase = app(PurchaseItem::class)->handle($owner, $data[2]);

    $achievement = mechanicAchievement($owner, 'ammunition-prepared');
    expect($achievement->progress)->toBe(3);
    expect($achievement->unlocked_at)->not->toBeNull();
    $offers[2]->item->update(['characteristics' => []]);
    expect(app(PurchaseItem::class)->handle($owner, $data[2])->id)->toBe($purchase->id);
    expect($achievement->fresh()->unlocked_at)->toEqual($achievement->unlocked_at);
    $this->assertDatabaseCount('item_purchases', 3);
    expect($owner->fresh()->experience)->toBe('0');
});

test('a titled puppy unlocks its original breeders achievement while competing for another owner', function () {
    $this->freezeSecond();
    $this->seed(AchievementSeeder::class);
    $breeder = User::factory()->create();
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();
    $litter = BreedingLitter::factory()->create(['initiator_id' => $breeder->id, 'born_at' => now()->subDay(), 'delivered_at' => now()->subDay()]);
    Puppy::factory()->for($litter, 'litter')->for($owner)->create(['pet_id' => $pet->id, 'status' => 'placed', 'placed_at' => now()->subHour()]);
    $event = GameEvent::factory()->create([
        'discipline' => 'canicross', 'status' => 'frozen', 'closes_at' => now()->subMinutes(20),
        'starts_at' => now()->subMinutes(10), 'ends_at' => now(),
    ]);
    $entry = GameEventEntry::factory()->for($event, 'event')->for($owner)->for($pet)->create([
        'status' => 'frozen',
        'snapshot' => [
            'version' => 1, 'name' => $pet->name,
            'stats' => array_fill_keys(array_column(PetStat::cases(), 'value'), 80),
            'states' => ['health' => 100, 'energy' => 100, 'bond' => 100, 'mood' => 100],
        ],
    ]);

    expect(app(GameEventProcessor::class)->processDue())->toBe(1);

    expect($entry->fresh()->rank)->toBe(1);
    expect($pet->titles()->count())->toBe(1);
    $achievement = mechanicAchievement($breeder, 'titled-offspring');
    expect($achievement->unlocked_at)->not->toBeNull();
    expect($breeder->fresh()->experience)->toBe('0');
    expect(app(GameEventProcessor::class)->processDue())->toBe(0);
    expect($achievement->fresh()->unlocked_at)->toEqual($achievement->unlocked_at);
});

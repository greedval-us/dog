<?php

use App\Models\Achievement;
use App\Models\BreedingLitter;
use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\ItemPurchase;
use App\Models\Pet;
use App\Models\PetTitle;
use App\Models\PlayerAchievement;
use App\Models\Puppy;
use App\Models\PuppyPlacement;
use App\Models\ShopOffer;
use App\Models\User;
use App\Modules\Players\Enums\AchievementMetric;
use App\Modules\Players\Queries\GetPlayerGameStatistics;
use App\Modules\Players\Services\PlayerProgress;
use Database\Seeders\AchievementSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->freezeSecond();
});

function recordedPlayerEvent(User $owner, Pet $pet, string $discipline, string $frequency, int $rank, int $fee, int $prize, bool $eliminated = false): GameEventEntry
{
    $startsAt = now()->addHours(2)->addMinutes(GameEvent::query()->count());
    $event = GameEvent::factory()->create([
        'discipline' => $discipline, 'frequency' => $frequency, 'status' => 'settled',
        'starts_at' => $startsAt, 'closes_at' => $startsAt->subMinutes(15), 'ends_at' => $startsAt->addMinutes(10),
    ]);

    return GameEventEntry::factory()->for($owner)->for($pet)->for($event, 'event')->create([
        'status' => 'completed', 'completed_at' => now()->subDay(), 'rank' => $rank,
        'fee' => $fee, 'prize' => $prize, 'result' => ['eliminated' => $eliminated], 'experience_awarded' => 20,
    ]);
}

function recordedPlayerTitle(GameEventEntry $entry): PetTitle
{
    return PetTitle::factory()->create([
        'pet_id' => $entry->pet_id, 'game_event_entry_id' => $entry->id,
        'discipline' => $entry->event->discipline, 'frequency' => $entry->event->frequency,
    ]);
}

test('public statistics aggregate saved human event receipts without losing history after a dog changes owners', function () {
    $owner = User::factory()->create(['coins' => 317, 'experience' => '90']);
    $viewer = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();
    $agility = recordedPlayerEvent($owner, $pet, 'agility', 'daily', 1, 25, 100);
    recordedPlayerEvent($owner, $pet, 'nosework', 'weekly', 2, 100, 300);
    $canicross = recordedPlayerEvent($owner, $pet, 'canicross', 'monthly', 1, 300, 2000);
    recordedPlayerEvent($owner, $pet, 'agility', 'daily', 1, 25, 777, true);
    $conformation = recordedPlayerEvent($owner, $pet, 'conformation', 'weekly', 1, 100, 500);
    recordedPlayerEvent($owner, $pet, 'progeny', 'monthly', 3, 300, 600);
    foreach ([$agility, $canicross, $conformation] as $entry) {
        recordedPlayerTitle($entry);
    }
    foreach (['registered', 'frozen', 'cancelled', 'withdrawn'] as $status) {
        recordedPlayerEvent($owner, $pet, 'agility', 'daily', 1, 25, 5000)->update(['status' => $status]);
    }
    GameEventEntry::factory()->for($agility->event, 'event')->create([
        'is_npc' => true, 'user_id' => null, 'pet_id' => null, 'status' => 'completed',
        'completed_at' => now(), 'rank' => 1, 'result' => ['eliminated' => false], 'fee' => 0, 'prize' => 0,
    ]);
    recordedPlayerEvent($viewer, $pet, 'nosework', 'daily', 1, 25, 100);
    $pet->update(['user_id' => $viewer->id]);

    $this->actingAs($viewer)->get(route('players.show', $owner->username))->assertInertia(fn (Assert $page) => $page
        ->where('player.dogsCount', 0)->where('player.competitionWins', 2)->where('player.exhibitionWins', 1)
        ->where('player.statistics.competitionStarts', 4)->where('player.statistics.competitionPodiums', 3)
        ->where('player.statistics.competitionWins', 2)->where('player.statistics.exhibitionStarts', 2)
        ->where('player.statistics.exhibitionPodiums', 2)->where('player.statistics.exhibitionWins', 1)
        ->where('player.statistics.agilityWins', 1)->where('player.statistics.noseworkWins', 0)
        ->where('player.statistics.canicrossWins', 1)->where('player.statistics.conformationWins', 1)
        ->where('player.statistics.progenyStarts', 1)->where('player.statistics.progenyWins', 0)
        ->where('player.statistics.weeklyEventWins', 1)->where('player.statistics.monthlyEventWins', 1)
        ->where('player.statistics.titlesCount', 3)->where('player.statistics.titledDogsCount', 1)
        ->where('player.statistics.eventPrizeCoins', 3500)->where('player.statistics.eventFeesCoins', 850));

    $this->assertDatabaseHas('users', ['id' => $owner->id, 'coins' => 317, 'experience' => '90', 'competition_wins' => 0]);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('incomplete malformed and eliminated receipts never become wins podiums or titles', function () {
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();
    $valid = recordedPlayerEvent($owner, $pet, 'agility', 'daily', 1, 25, 100);
    $invalid = [
        ['result' => null], ['result' => []], ['rank' => null], ['completed_at' => null],
        ['result' => ['eliminated' => true]],
    ];
    foreach ($invalid as $attributes) {
        $entry = recordedPlayerEvent($owner, $pet, 'agility', 'daily', 1, 25, 999);
        $entry->update($attributes);
        recordedPlayerTitle($entry);
    }
    recordedPlayerEvent($owner, $pet, 'agility', 'daily', 1, 25, 999)->event->update(['status' => 'cancelled']);

    $statistics = app(GetPlayerGameStatistics::class)->handle($owner);

    expect($statistics->competitionStarts)->toBe(3);
    expect($statistics->competitionPodiums)->toBe(1);
    expect($statistics->competitionWins)->toBe(1);
    expect($statistics->titlesCount)->toBe(0);
    expect($statistics->eventPrizeCoins)->toBe(100);
    expect($statistics->eventFeesCoins)->toBe(75);
});

test('breeding and puppy trade statistics follow initiator and placement receipts across later handoffs', function () {
    $owner = User::factory()->create();
    $buyer = User::factory()->create();
    $parent = Pet::factory()->for($owner)->create();
    $first = BreedingLitter::factory()->for($parent, 'ownPet')->create(['initiator_id' => $owner->id, 'delivered_at' => now()->subDays(2)]);
    $second = BreedingLitter::factory()->for($parent, 'ownPet')->create(['initiator_id' => $owner->id, 'delivered_at' => now()->subDay()]);
    $pending = BreedingLitter::factory()->for($parent, 'ownPet')->create(['initiator_id' => $owner->id]);
    $foreign = BreedingLitter::factory()->for($parent, 'ownPet')->create(['initiator_id' => $buyer->id, 'delivered_at' => now()]);
    $descendant = Pet::factory()->for($buyer)->create();
    $kept = Puppy::factory()->for($first, 'litter')->create(['user_id' => $owner->id, 'status' => 'placed']);
    $sold = Puppy::factory()->for($second, 'litter')->create(['user_id' => $buyer->id, 'status' => 'placed', 'pet_id' => $descendant->id]);
    Puppy::factory()->for($pending, 'litter')->create(['user_id' => $owner->id, 'status' => 'unborn']);
    $purchased = Puppy::factory()->for($foreign, 'litter')->create(['user_id' => $owner->id, 'status' => 'placed']);
    PuppyPlacement::factory()->for($kept)->for($owner)->create(['kind' => 'keep']);
    PuppyPlacement::factory()->for($sold)->for($buyer)->create(['kind' => 'purchase', 'seller_id' => $owner->id, 'price' => 120]);
    PuppyPlacement::factory()->for($purchased)->for($owner)->create(['kind' => 'purchase', 'seller_id' => $buyer->id, 'price' => 90]);
    $win = recordedPlayerEvent($buyer, $descendant, 'conformation', 'weekly', 1, 100, 500);
    recordedPlayerTitle($win);
    recordedPlayerTitle(recordedPlayerEvent($buyer, $descendant, 'nosework', 'monthly', 1, 300, 2000));
    $parent->update(['user_id' => $buyer->id]);

    $statistics = app(GetPlayerGameStatistics::class)->handle($owner);

    expect($statistics->littersStarted)->toBe(3);
    expect($statistics->littersBorn)->toBe(2);
    expect($statistics->puppiesBorn)->toBe(2);
    expect($statistics->puppiesKept)->toBe(1);
    expect($statistics->puppiesPurchased)->toBe(1);
    expect($statistics->puppiesSold)->toBe(1);
    expect($statistics->puppySalesCoins)->toBe(120);
    expect($statistics->titledOffspring)->toBe(1);
    expect($statistics->titlesCount)->toBe(0);
});

test('ammunition purchase statistics use the durable item snapshot rather than the current catalogue or inventory', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $offer = ShopOffer::factory()->create();
    $snapshot = $offer->item->inventorySnapshot();
    $snapshot['characteristics']['competition'] = ['slot' => 'body', 'disciplines' => ['agility']];
    ItemPurchase::factory()->count(3)->for($owner)->for($offer, 'offer')->create(['item_snapshot' => $snapshot]);
    ItemPurchase::factory()->for($other)->for($offer, 'offer')->create(['item_snapshot' => $snapshot]);
    foreach ([null, 'body'] as $invalid) {
        ItemPurchase::factory()->for($owner)->for($offer, 'offer')->create([
            'item_snapshot' => ['characteristics' => ['competition' => $invalid]],
        ]);
    }
    ItemPurchase::factory()->for($owner)->for($offer, 'offer')->create(['item_snapshot' => ['characteristics' => []]]);
    $offer->item->update(['characteristics' => ['competition' => ['slot' => 'body']]]);

    expect(app(GetPlayerGameStatistics::class)->handle($owner)->ammunitionPurchases)->toBe(3);

    $this->assertDatabaseCount('inventory_items', 0);
});

test('historical results unlock the new achievement cards on viewing without replaying rewards', function () {
    $this->seed(AchievementSeeder::class);
    $owner = User::factory()->create(['coins' => 317, 'gems' => 9, 'experience' => '90']);
    $pet = Pet::factory()->for($owner)->create();
    recordedPlayerEvent($owner, $pet, 'agility', 'daily', 1, 25, 100)->update(['experience_awarded' => null]);
    recordedPlayerEvent($owner, $pet, 'nosework', 'weekly', 1, 100, 500);
    recordedPlayerEvent($owner, $pet, 'canicross', 'monthly', 1, 300, 2000);
    recordedPlayerEvent($owner, $pet, 'conformation', 'daily', 1, 25, 100);
    recordedPlayerEvent($owner, $pet, 'progeny', 'daily', 3, 25, 40);

    $this->actingAs($owner)->get(route('players.achievements', $owner->username))->assertInertia(fn (Assert $page) => $page->has('achievements', 35));
    $unlocked = PlayerAchievement::query()->where('user_id', $owner->id)->whereNotNull('unlocked_at')
        ->join('achievements', 'achievements.id', '=', 'player_achievements.achievement_id')->pluck('code')->all();

    expect($unlocked)->toContain('first-competition', 'first-competition-podium', 'competition-winner',
        'agility-winner', 'nosework-winner', 'canicross-winner', 'first-exhibition', 'first-exhibition-podium',
        'exhibition-winner', 'weekly-champion', 'monthly-champion', 'first-progeny-show');
    $starts = Achievement::query()->where('code', 'competition-20')->sole();
    $this->assertDatabaseHas('player_achievements', ['user_id' => $owner->id, 'achievement_id' => $starts->id, 'progress' => 3, 'unlocked_at' => null]);
    $savedUnlocks = PlayerAchievement::query()->where('user_id', $owner->id)->pluck('unlocked_at', 'achievement_id')->all();
    $this->travel(1)->days();
    $this->get(route('players.achievements', $owner->username))->assertOk();
    expect(PlayerAchievement::query()->where('user_id', $owner->id)->pluck('unlocked_at', 'achievement_id')->all())->toEqual($savedUnlocks);
    $this->assertDatabaseHas('users', ['id' => $owner->id, 'coins' => 317, 'gems' => 9, 'experience' => '90']);
    $this->assertDatabaseCount('currency_transactions', 0);
    expect(GameEventEntry::query()->where('user_id', $owner->id)->pluck('experience_awarded')->unique()->values()->all())->toBe([null, 20]);
});

test('twenty completed competitions unlock the lifetime start target and exclude unfinished registrations', function () {
    $this->seed(AchievementSeeder::class);
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();
    for ($index = 0; $index < 19; $index++) {
        recordedPlayerEvent($owner, $pet, 'agility', 'daily', 4, 25, 0);
    }
    $last = recordedPlayerEvent($owner, $pet, 'agility', 'daily', 4, 25, 0);
    $last->update(['status' => 'frozen', 'completed_at' => null]);
    app(PlayerProgress::class)->refreshAchievements($owner);
    $achievement = Achievement::query()->where('code', 'competition-20')->sole();
    $this->assertDatabaseHas('player_achievements', ['user_id' => $owner->id, 'achievement_id' => $achievement->id, 'progress' => 19, 'unlocked_at' => null]);
    $last->update(['status' => 'completed', 'completed_at' => now()]);

    app(PlayerProgress::class)->refreshAchievements($owner);

    $state = PlayerAchievement::query()->where('user_id', $owner->id)->where('achievement_id', $achievement->id)->sole();
    expect($state->progress)->toBe(20);
    expect($state->unlocked_at)->not->toBeNull();
    $this->assertDatabaseHas('users', ['id' => $owner->id, 'experience' => '0']);
});

test('litter and puppy achievements use delivered births and unlock at five litters and ten puppies', function () {
    $this->seed(AchievementSeeder::class);
    $owner = User::factory()->create();
    $parent = Pet::factory()->for($owner)->create();
    for ($index = 0; $index < 5; $index++) {
        $litter = BreedingLitter::factory()->for($parent, 'ownPet')->create([
            'initiator_id' => $owner->id, 'born_at' => now()->subDay(), 'delivered_at' => $index < 4 ? now() : null,
        ]);
        Puppy::factory()->count(2)->for($litter, 'litter')->create(['status' => $index < 4 ? 'pending' : 'unborn']);
    }
    app(PlayerProgress::class)->refreshAchievements($owner);
    $litters = Achievement::query()->where('code', 'breeder-5')->sole();
    $puppies = Achievement::query()->where('code', 'puppies-10')->sole();
    $this->assertDatabaseHas('player_achievements', ['user_id' => $owner->id, 'achievement_id' => $litters->id, 'progress' => 4, 'unlocked_at' => null]);
    $this->assertDatabaseHas('player_achievements', ['user_id' => $owner->id, 'achievement_id' => $puppies->id, 'progress' => 8, 'unlocked_at' => null]);
    $litter->update(['delivered_at' => now()]);

    app(PlayerProgress::class)->refreshAchievements($owner);

    expect(PlayerAchievement::query()->where('user_id', $owner->id)->where('achievement_id', $litters->id)->sole()->unlocked_at)->not->toBeNull();
    expect(PlayerAchievement::query()->where('user_id', $owner->id)->where('achievement_id', $puppies->id)->sole()->unlocked_at)->not->toBeNull();
    $this->assertDatabaseHas('users', ['id' => $owner->id, 'experience' => '0']);
});

test('bounded historical metrics read only their relevant receipts', function () {
    $owner = User::factory()->create();
    BreedingLitter::factory()->create(['initiator_id' => $owner->id, 'delivered_at' => now()]);
    DB::enableQueryLog();
    DB::flushQueryLog();

    try {
        $statistics = app(GetPlayerGameStatistics::class)->handle($owner, [AchievementMetric::LittersBorn]);
        $queries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    expect($statistics->littersBorn)->toBe(1);
    expect($queries)->toHaveCount(1);
    expect($queries[0]['query'])->toContain('breeding_litters')->not->toContain('game_event_entries', 'item_purchases', 'puppy_placements');
});

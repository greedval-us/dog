<?php

use App\Models\Achievement;
use App\Models\Dog;
use App\Models\Pet;
use App\Models\PetCareAction;
use App\Models\PlayerAchievement;
use App\Models\User;
use App\Modules\Kennel\Actions\AdoptStarterPet;
use App\Modules\Kennel\Actions\PurchaseKennelPet;
use App\Modules\Kennel\DTO\AdoptStarterPetData;
use App\Modules\Kennel\DTO\PurchaseKennelPetData;
use App\Modules\Pets\Actions\CompletePetCare;
use App\Modules\Pets\Actions\StartPetCare;
use App\Modules\Players\Services\PlayerProgress;
use Carbon\CarbonImmutable;
use Database\Seeders\AchievementSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->freezeSecond();
});

function achievementForCode(string $code): Achievement
{
    return Achievement::query()->where('code', $code)->sole();
}

function achievementProgress(User $player, string $code): PlayerAchievement
{
    return PlayerAchievement::query()->where('user_id', $player->id)
        ->where('achievement_id', achievementForCode($code)->id)->sole();
}

test('guests must sign in to view player achievements', function () {
    $player = User::factory()->create();

    $this->get(route('players.achievements', $player->username))->assertRedirect(route('login'));

    $this->assertDatabaseCount('player_achievements', 0);
});

test('the achievement catalogue seeds fifteen complete bilingual cards with available images', function () {
    $this->seed(AchievementSeeder::class);

    $this->seed(AchievementSeeder::class);

    $this->assertDatabaseCount('achievements', 15);
    foreach (Achievement::query()->get() as $achievement) {
        foreach (['ru', 'en'] as $locale) {
            expect($achievement->name[$locale])->toBeString()->not->toBeEmpty();
            expect($achievement->description[$locale])->toBeString()->not->toBeEmpty();
            expect($achievement->rule_description[$locale])->toBeString()->not->toBeEmpty();
        }
        expect(public_path(ltrim($achievement->image_path, '/')))->toBeFile();
    }
});

test('players can view another players achievements in their own language with isolated progress', function (string $locale, string $name, string $description, string $rule) {
    $this->seed(AchievementSeeder::class);
    $achievement = achievementForCode('first-dog');
    $achievement->update([
        'name' => ['ru' => 'Верный хвост', 'en' => 'Faithful tail'],
        'description' => ['ru' => 'Теперь у вас есть друг.', 'en' => 'You have a friend now.'],
        'rule_description' => ['ru' => 'Заведите первую собаку.', 'en' => 'Adopt your first dog.'],
    ]);
    $viewer = User::factory()->create(['locale' => $locale, 'trainings_count' => 100]);
    $player = User::factory()->create(['locale' => $locale === 'ru' ? 'en' : 'ru', 'trainings_count' => 49]);

    $this->actingAs($viewer)->get(route('players.achievements', $player->username))
        ->assertInertia(fn (Assert $page) => $page
            ->component('players/Achievements')
            ->where('player.username', $player->username)
            ->where('auth.user.id', $viewer->id)
            ->has('achievements', 15)
            ->where('totalCount', 15)
            ->where('unlockedCount', 1)
            ->where('achievements.0.code', 'first-dog')
            ->where('achievements.0.name', $name)
            ->where('achievements.0.description', $description)
            ->where('achievements.0.rule', $rule)
            ->where('achievements.0.imageUrl', asset($achievement->image_path))
            ->where('achievements.0.unlockedAt', null)
            ->where('achievements.4.code', 'training-50')
            ->where('achievements.4.progress', 49)
            ->where('achievements.4.target', 50)
            ->where('achievements.4.unlockedAt', null)
        )->assertDontSee($player->email);

    $this->assertDatabaseMissing('player_achievements', ['user_id' => $viewer->id]);
    expect(achievementProgress($player, 'training-50')->progress)->toBe(49);
})->with([
    'Russian viewer' => ['ru', 'Верный хвост', 'Теперь у вас есть друг.', 'Заведите первую собаку.'],
    'English viewer' => ['en', 'Faithful tail', 'You have a friend now.', 'Adopt your first dog.'],
]);

test('the achievements page returns not found for a missing public username', function () {
    $viewer = User::factory()->create();

    $this->actingAs($viewer)->get(route('players.achievements', 'missing_player'))->assertNotFound();
});

test('training achievements unlock only at fifty and one hundred completed trainings', function (int $trainings, bool $fiftyUnlocked, bool $hundredUnlocked) {
    $this->seed(AchievementSeeder::class);
    $player = User::factory()->create(['trainings_count' => $trainings]);

    app(PlayerProgress::class)->refreshAchievements($player);

    expect(achievementProgress($player, 'training-50')->unlocked_at !== null)->toBe($fiftyUnlocked);
    expect(achievementProgress($player, 'training-100')->unlocked_at !== null)->toBe($hundredUnlocked);
})->with([
    'forty nine trainings' => [49, false, false],
    'fifty trainings' => [50, true, false],
    'ninety nine trainings' => [99, true, false],
    'one hundred trainings' => [100, true, true],
]);

test('existing player statistics backfill the corresponding achievement without rewards', function (string $code, array $attributes) {
    $this->seed(AchievementSeeder::class);
    $player = User::factory()->create([
        ...$attributes, 'coins' => 317, 'gems' => 9, 'experience' => '90',
    ]);

    app(PlayerProgress::class)->refreshAchievements($player);

    expect(achievementProgress($player, $code)->unlocked_at)->not->toBeNull();
    $this->assertDatabaseHas('users', ['id' => $player->id, 'coins' => 317, 'gems' => 9, 'experience' => '90']);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with([
    'first training' => ['first-training', ['trainings_count' => 1]],
    'first meal' => ['first-meal', ['pet_statistics' => ['care.meal' => 1]]],
    'first walk' => ['first-walk', ['walks_count' => 1]],
    'fifty walks' => ['walk-50', ['walks_count' => 50]],
    'first wash' => ['first-wash', ['pet_statistics' => ['care.wash' => 1]]],
    'first skill' => ['first-skill', ['pet_statistics' => ['skill_training' => 1]]],
    'first job' => ['first-job', ['pet_statistics' => ['work' => 1]]],
    'three veterinary services' => ['vet-regular', ['pet_statistics' => [
        'veterinary.checkup' => 1, 'veterinary.vaccination' => 1, 'veterinary.treatment' => 1,
    ]]],
    'seven active days' => ['week-together', ['active_days' => 7]],
]);

test('an unlocked achievement keeps its first date after refreshes and a later lower counter', function () {
    $this->seed(AchievementSeeder::class);
    $player = User::factory()->create(['trainings_count' => 50, 'coins' => 317, 'gems' => 9, 'experience' => '90']);
    app(PlayerProgress::class)->refreshAchievements($player);
    $achievement = achievementProgress($player, 'training-50');
    $unlockedAt = $achievement->unlocked_at->toISOString();
    $this->travel(1)->days();
    $player->forceFill(['trainings_count' => 0])->save();

    app(PlayerProgress::class)->refreshAchievements($player);
    app(PlayerProgress::class)->refreshAchievements($player);

    expect(achievementProgress($player, 'training-50')->id)->toBe($achievement->id);
    expect(achievementProgress($player, 'training-50')->unlocked_at->toISOString())->toBe($unlockedAt);
    $this->assertDatabaseHas('users', ['id' => $player->id, 'coins' => 317, 'gems' => 9, 'experience' => '90']);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('the pack achievement counts only the players active dogs', function () {
    $this->seed(AchievementSeeder::class);
    $player = User::factory()->create();
    Pet::factory()->for($player)->count(2)->create();
    Pet::factory()->for($player)->retired()->create();
    Pet::factory()->for($player)->deceased()->create();
    Pet::factory()->count(3)->create();
    app(PlayerProgress::class)->refreshAchievements($player);
    expect(achievementProgress($player, 'pack-leader')->unlocked_at)->toBeNull();
    expect(achievementProgress($player, 'pack-leader')->progress)->toBe(2);
    Pet::factory()->for($player)->create();

    app(PlayerProgress::class)->refreshAchievements($player);

    expect(achievementProgress($player, 'pack-leader')->unlocked_at)->not->toBeNull();
});

test('an archived first dog unlocks the first dog achievement without a starter claim or purchase receipt', function (string $state) {
    $this->seed(AchievementSeeder::class);
    $player = User::factory()->create(['starter_pet_claimed_at' => null]);
    Pet::factory()->for($player)->{$state}()->create();

    app(PlayerProgress::class)->refreshAchievements($player);

    expect(achievementProgress($player, 'first-dog')->unlocked_at)->not->toBeNull();
    expect(PlayerAchievement::query()->where('user_id', $player->id)
        ->where('achievement_id', achievementForCode('pack-leader')->id)->value('unlocked_at'))->toBeNull();
    $this->assertDatabaseHas('users', ['id' => $player->id, 'starter_pet_claimed_at' => null]);
    $this->assertDatabaseCount('kennel_purchases', 0);
})->with(['retired dog' => ['retired'], 'deceased dog' => ['deceased']]);

test('fifteen meals require completed feeding of the same dog on one Moscow calendar day', function () {
    $this->seed(AchievementSeeder::class);
    config(['doglive.work_timezone' => 'Europe/Moscow']);
    $player = User::factory()->create();
    $first = Pet::factory()->for($player)->create();
    $second = Pet::factory()->for($player)->create();
    $other = Pet::factory()->create();
    $early = CarbonImmutable::parse('2026-10-01 21:05:00', 'UTC');
    $late = CarbonImmutable::parse('2026-10-02 20:55:00', 'UTC');
    $nextDay = CarbonImmutable::parse('2026-10-02 21:05:00', 'UTC');
    foreach ([$early, $late] as $completedAt) {
        PetCareAction::factory()->count(7)->create([
            'pet_id' => $first->id, 'user_id' => $player->id, 'group' => 'feed', 'variant' => 'meal',
            'ends_at' => $completedAt, 'completed_at' => $completedAt, 'experience_awarded' => 10,
        ]);
    }
    PetCareAction::factory()->create([
        'pet_id' => $first->id, 'user_id' => $player->id, 'group' => 'feed', 'variant' => 'meal',
        'ends_at' => $nextDay, 'completed_at' => $nextDay, 'experience_awarded' => 10,
    ]);
    PetCareAction::factory()->count(14)->create([
        'pet_id' => $second->id, 'user_id' => $player->id, 'group' => 'feed', 'variant' => 'meal',
        'ends_at' => $late, 'completed_at' => $late, 'experience_awarded' => 10,
    ]);
    PetCareAction::factory()->count(15)->create([
        'pet_id' => $other->id, 'user_id' => $other->user_id, 'group' => 'feed', 'variant' => 'meal',
        'ends_at' => $late, 'completed_at' => $late, 'experience_awarded' => 10,
    ]);
    PetCareAction::factory()->create([
        'pet_id' => $first->id, 'user_id' => $player->id, 'group' => 'feed', 'variant' => 'meal',
        'ends_at' => $late, 'completed_at' => $late, 'cancelled_at' => $late,
    ]);
    PetCareAction::factory()->create([
        'pet_id' => $first->id, 'user_id' => $player->id, 'group' => 'feed', 'variant' => 'meal',
        'ends_at' => $late, 'completed_at' => null,
    ]);
    PetCareAction::factory()->create([
        'pet_id' => $first->id, 'user_id' => $player->id, 'group' => 'feed', 'variant' => 'water',
        'ends_at' => $late, 'completed_at' => $late, 'experience_awarded' => 10,
    ]);
    app(PlayerProgress::class)->refreshAchievements($player);
    expect(achievementProgress($player, 'bottomless-bowl')->progress)->toBe(14);
    expect(achievementProgress($player, 'bottomless-bowl')->unlocked_at)->toBeNull();
    PetCareAction::factory()->create([
        'pet_id' => $first->id, 'user_id' => $player->id, 'group' => 'feed', 'variant' => 'meal',
        'ends_at' => $late, 'completed_at' => $late, 'experience_awarded' => 10,
    ]);

    app(PlayerProgress::class)->refreshAchievements($player);

    expect(achievementProgress($player, 'bottomless-bowl')->unlocked_at)->not->toBeNull();
});

test('dog adoption and kennel purchases unlock achievements inside the acquisition actions', function () {
    $this->seed(AchievementSeeder::class);
    config(['doglive.kennel_price' => 500]);
    $dog = Dog::factory()->create(['is_starter' => true]);
    $player = User::factory()->create(['coins' => 2000, 'pet_slots' => 4]);

    app(AdoptStarterPet::class)->handle($player, new AdoptStarterPetData($dog->id, 'Рэй'));

    expect(achievementProgress($player, 'first-dog')->unlocked_at)->not->toBeNull();
    $this->assertDatabaseHas('users', ['id' => $player->id, 'coins' => 2000, 'experience' => '0']);
    $this->assertDatabaseCount('currency_transactions', 0);
    foreach (['Бим', 'Бом', 'Бам'] as $name) {
        $data = new PurchaseKennelPetData($dog->id, $name, 500, (string) Str::uuid());
        app(PurchaseKennelPet::class)->handle($player, $data);
    }
    app(PurchaseKennelPet::class)->handle($player, $data);

    expect(achievementProgress($player, 'kennel-regular')->unlocked_at)->not->toBeNull();
    expect(achievementProgress($player, 'pack-leader')->unlocked_at)->not->toBeNull();
    $this->assertDatabaseHas('users', ['id' => $player->id, 'coins' => 500, 'experience' => '0']);
    $this->assertDatabaseCount('kennel_purchases', 3);
    $this->assertDatabaseCount('currency_transactions', 3);
});

test('care completion unlocks its achievement once and replay grants no extra progress or rewards', function () {
    $this->seed(AchievementSeeder::class);
    $player = User::factory()->create(['coins' => 317, 'gems' => 9]);
    $pet = Pet::factory()->for($player)->create(['energy' => 100, 'energy_max' => 100, 'cleanliness' => 50, 'cleanliness_max' => 100]);
    $care = app(StartPetCare::class)->handle($player, $pet->id, 'wash', [], (string) Str::uuid());
    expect(PlayerAchievement::query()->where('user_id', $player->id)
        ->where('achievement_id', achievementForCode('first-wash')->id)->value('unlocked_at'))->toBeNull();
    $this->travelTo($care->ends_at);

    expect(app(CompletePetCare::class)->handle($player, $pet->id, $care->token))->toBeTrue();
    $unlockedAt = achievementProgress($player, 'first-wash')->unlocked_at->toISOString();
    $this->travel(1)->minutes();
    expect(app(CompletePetCare::class)->handle($player, $pet->id, $care->token))->toBeFalse();
    expect(app(PlayerProgress::class)->award($player, $care))->toBe(10);

    expect(achievementProgress($player, 'first-wash')->unlocked_at->toISOString())->toBe($unlockedAt);
    expect($player->fresh()->pet_statistics)->toBe(['care.wash' => 1]);
    $this->assertDatabaseHas('users', ['id' => $player->id, 'coins' => 317, 'gems' => 9, 'experience' => '10']);
    $this->assertDatabaseHas('pet_care_actions', ['id' => $care->id, 'experience_awarded' => 10]);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('a failed achievement unlock rolls back the action reward and receipt marker until retry succeeds', function () {
    $this->seed(AchievementSeeder::class);
    $pet = Pet::factory()->create();
    $receipt = PetCareAction::factory()->create([
        'pet_id' => $pet->id, 'user_id' => $pet->user_id, 'group' => 'groom', 'variant' => 'wash',
        'ends_at' => now(), 'completed_at' => now(),
    ]);
    DB::unprepared("CREATE FUNCTION reject_achievement_unlock() RETURNS trigger LANGUAGE plpgsql AS 'BEGIN IF NEW.unlocked_at IS NOT NULL THEN RAISE EXCEPTION ''Simulated achievement failure''; END IF; RETURN NEW; END'; CREATE TRIGGER reject_achievement_unlock BEFORE INSERT OR UPDATE ON player_achievements FOR EACH ROW EXECUTE FUNCTION reject_achievement_unlock()");

    try {
        expect(fn () => app(PlayerProgress::class)->award($pet->user, $receipt))->toThrow(QueryException::class);
    } finally {
        DB::unprepared('DROP TRIGGER reject_achievement_unlock ON player_achievements; DROP FUNCTION reject_achievement_unlock()');
    }

    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'experience' => '0', 'active_days' => 0]);
    expect($pet->user->fresh()->pet_statistics)->toBe([]);
    $this->assertDatabaseHas('pet_care_actions', ['id' => $receipt->id, 'experience_awarded' => null]);
    $this->assertDatabaseCount('player_achievements', 0);
    expect(app(PlayerProgress::class)->award($pet->user, $receipt))->toBe(10);
    expect(achievementProgress($pet->user, 'first-wash')->unlocked_at)->not->toBeNull();
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'experience' => '10', 'active_days' => 1]);
});

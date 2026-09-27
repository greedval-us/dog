<?php

use App\Models\User;
use App\Models\WorkShift;
use App\Models\WorkType;
use App\Modules\Players\Actions\CompleteDailyWork;
use App\Modules\Players\Enums\PlayerStatus;
use App\Modules\Players\Exceptions\WorkUnavailable;
use Carbon\CarbonImmutable;
use Database\Seeders\WorkTypeSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse('2026-09-27 12:00:00', 'Europe/Moscow'));
});

test('guests cannot collect work rewards', function () {
    $this->post(route('daily-work.store'), ['work_type_id' => 1])->assertRedirect(route('login'));
    $this->assertDatabaseCount('work_shifts', 0);
});

test('blocked players cannot work through http or the action', function () {
    $user = User::factory()->create(['status' => PlayerStatus::Blocked]);
    $work = WorkType::factory()->create();

    $this->actingAs($user)->post(route('daily-work.store'), ['work_type_id' => $work->id])->assertForbidden();
    expect(fn () => app(CompleteDailyWork::class)->handle($user, $work->id))->toThrow(WorkUnavailable::class);
    $this->assertDatabaseCount('work_shifts', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('work validates the selected type', function (mixed $value) {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('daily-work.store'), ['work_type_id' => $value])
        ->assertSessionHasErrors('work_type_id');
    $this->assertDatabaseCount('work_shifts', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with(['missing' => null, 'text' => 'invalid', 'negative' => -1, 'fraction' => 1.5]);

test('unavailable work cannot grant a reward', function (array $attributes, bool $missing) {
    $user = User::factory()->create();
    $work = WorkType::factory()->create($attributes);

    $this->actingAs($user)->post(route('daily-work.store'), ['work_type_id' => $missing ? $work->id + 1 : $work->id])
        ->assertSessionHasErrors('work_type_id');
    $this->assertDatabaseCount('work_shifts', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with([
    'inactive' => [['is_active' => false], false],
    'zero reward' => [['coins_reward' => 0], false],
    'missing type' => [[], true],
]);

test('work pays only the authenticated player once per day across all work types', function () {
    $user = User::factory()->create(['coins' => 10, 'gems' => 2]);
    $other = User::factory()->create(['coins' => 7]);
    $work = WorkType::factory()->create(['coins_reward' => 35]);
    $anotherWork = WorkType::factory()->create();
    $payload = ['work_type_id' => $work->id, 'user_id' => $other->id, 'coins_reward' => 9999, 'streak_day' => 5];

    $this->actingAs($user)->post(route('daily-work.store'), $payload)->assertRedirect(route('players.show', $user->username));
    $work->update(['coins_reward' => 100]);
    $this->post(route('daily-work.store'), $payload)->assertSessionHasNoErrors();
    $this->post(route('daily-work.store'), ['work_type_id' => $anotherWork->id])->assertSessionHasErrors('work_type_id');

    $this->assertDatabaseHas('users', ['id' => $user->id, 'coins' => 45, 'gems' => 2]);
    $this->assertDatabaseHas('users', ['id' => $other->id, 'coins' => 7]);
    $this->assertDatabaseCount('work_shifts', 1);
    $this->assertDatabaseHas('work_shifts', ['user_id' => $user->id, 'worked_on' => '2026-09-27 00:00:00', 'streak_day' => 1, 'coins_reward' => 35, 'gems_reward' => 0]);
    $this->assertDatabaseCount('currency_transactions', 1);
});

test('every fifth consecutive day awards gems once and the cycle restarts', function () {
    $user = User::factory()->create(['coins' => 0, 'gems' => 0]);
    $work = WorkType::factory()->create();
    $this->actingAs($user);

    for ($day = 1; $day <= 10; $day++) {
        $this->post(route('daily-work.store'), ['work_type_id' => $work->id])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'coins' => $day * 50, 'gems' => $day < 5 ? 0 : ($day < 10 ? 5 : 10)]);
        if ($day < 10) {
            $this->travel(1)->days();
        }
    }
    $this->post(route('daily-work.store'), ['work_type_id' => $work->id])->assertSessionHasNoErrors();

    $this->assertDatabaseCount('work_shifts', 10);
    $this->assertDatabaseCount('currency_transactions', 12);
    $this->get(route('players.show', $user->username))->assertInertia(fn (Assert $page) => $page
        ->where('dailyWork.progress', 5)->where('dailyWork.earnedGems', 5)->where('dailyWork.completedToday', true));
    $this->travel(1)->days();
    $this->get(route('players.show', $user->username))->assertInertia(fn (Assert $page) => $page
        ->where('dailyWork.progress', 0)->where('dailyWork.canWork', true));
});

test('missing a calendar day resets the series without a bonus', function () {
    $user = User::factory()->create();
    $work = WorkType::factory()->create();
    WorkShift::factory()->for($user)->for($work)->create(['worked_on' => '2026-09-25', 'streak_day' => 4]);

    $this->actingAs($user)->get(route('players.show', $user->username))->assertInertia(fn (Assert $page) => $page->where('dailyWork.progress', 0));
    $this->post(route('daily-work.store'), ['work_type_id' => $work->id])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('work_shifts', ['user_id' => $user->id, 'worked_on' => '2026-09-27 00:00:00', 'streak_day' => 1, 'gems_reward' => 0]);
});

test('midnight in the game timezone opens a new shift independently of user timezone', function () {
    $user = User::factory()->create(['timezone' => 'Pacific/Honolulu']);
    $work = WorkType::factory()->create();
    $this->travelTo(CarbonImmutable::parse('2026-09-27 20:59:59', 'UTC'));
    $this->actingAs($user)->post(route('daily-work.store'), ['work_type_id' => $work->id])->assertSessionHasNoErrors();
    $this->travel(1)->seconds();

    $this->post(route('daily-work.store'), ['work_type_id' => $work->id])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('work_shifts', ['user_id' => $user->id, 'worked_on' => '2026-09-28 00:00:00', 'streak_day' => 2]);
    $this->assertDatabaseHas('users', ['id' => $user->id, 'coins' => 100]);
});

test('a failed bonus payment rolls back the shift and coin payment', function () {
    $user = User::factory()->create(['coins' => 12, 'gems' => PHP_INT_MAX]);
    $work = WorkType::factory()->create();
    WorkShift::factory()->for($user)->for($work)->create(['worked_on' => '2026-09-26', 'streak_day' => 4]);

    expect(fn () => app(CompleteDailyWork::class)->handle($user, $work->id))->toThrow(InvalidArgumentException::class);

    $this->assertDatabaseCount('work_shifts', 1);
    $this->assertDatabaseCount('currency_transactions', 0);
    $this->assertDatabaseHas('users', ['id' => $user->id, 'coins' => 12, 'gems' => PHP_INT_MAX]);
});

test('the work card is private and shows localized catalogue rewards and progress', function (string $locale, string $name) {
    $user = User::factory()->create(['locale' => $locale]);
    $viewer = User::factory()->create();
    $work = WorkType::factory()->create();
    WorkType::factory()->create(['is_active' => false]);
    WorkShift::factory()->for($user)->for($work)->create(['worked_on' => '2026-09-26', 'streak_day' => 3]);

    $this->actingAs($user)->get(route('players.show', $user->username))->assertInertia(fn (Assert $page) => $page
        ->where('dailyWork.canWork', true)->where('dailyWork.progress', 3)
        ->where('dailyWork.resetsAt', '2026-09-28T00:00:00+03:00')
        ->has('dailyWork.jobs', 1)->where('dailyWork.jobs.0.name', $name)
        ->where('dailyWork.jobs.0.coins', 50)->where('dailyWork.jobs.0.gems', 5));
    $this->actingAs($viewer)->get(route('players.show', $user->username))->assertInertia(fn (Assert $page) => $page->where('dailyWork', null));
})->with([['ru', 'Помощник в приюте'], ['en', 'Shelter helper']]);

test('the starter work seeder is repeatable and preserves adjusted rewards', function () {
    $this->seed(WorkTypeSeeder::class);
    WorkType::query()->where('code', 'shelter_helper')->update(['coins_reward' => 75]);

    $this->seed(WorkTypeSeeder::class);

    $this->assertDatabaseCount('work_types', 1);
    $this->assertDatabaseHas('work_types', ['code' => 'shelter_helper', 'coins_reward' => 75]);
});

<?php

use App\Http\Telegram\Menus\DailyWorkMenu;
use App\Models\User;
use App\Models\WorkShift;
use App\Models\WorkType;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Players\Enums\PlayerStatus;
use App\Modules\Players\Exceptions\WorkUnavailable;
use Carbon\CarbonImmutable;

test('telegram daily work lists localized jobs and the current streak without collecting rewards', function (string $locale, string $name) {
    $this->travelTo(CarbonImmutable::parse('2026-09-27 12:00:00', 'Europe/Moscow'));
    $user = User::factory()->create(['coins' => 10, 'gems' => 2, 'timezone' => 'Pacific/Honolulu']);
    $work = WorkType::factory()->create();
    WorkType::factory()->create(['is_active' => false]);
    WorkShift::factory()->for($user)->for($work)->create(['worked_on' => '2026-09-26', 'streak_day' => 3]);

    $screen = app(DailyWorkMenu::class)->index($user, $locale);

    expect($screen->text)->toContain('3/5', 'Europe/Moscow');
    expect(collect($screen->rows)->flatten(1)->pluck('text')->all())->toContain($name.' · 50 '.trans('telegram.coins', [], $locale));
    expect(collect($screen->rows)->flatten(1)->pluck('command')->filter()->values()->all())->toContain([
        'action' => 'daily_work.confirm', 'workTypeId' => $work->id,
    ]);
    expect($screen->state)->toBeEmpty();
    $this->assertDatabaseHas('users', ['id' => $user->id, 'coins' => 10, 'gems' => 2]);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with([['en', 'Shelter helper'], ['ru', 'Помощник в приюте']]);

test('telegram daily work confirmation escapes catalogue text and requires a separate completion', function () {
    $this->freezeSecond();
    $user = User::factory()->create(['coins' => 10, 'gems' => 2]);
    $work = WorkType::factory()->create([
        'name' => ['en' => '<job>'], 'description' => ['en' => 'Help <dogs> & cats.'],
    ]);

    $screen = app(DailyWorkMenu::class)->confirm($user, 'en', ['workTypeId' => $work->id]);

    expect($screen->text)->toContain('&lt;job&gt;', 'Help &lt;dogs&gt; &amp; cats.')->not->toContain('<job>', '<dogs>');
    expect(collect($screen->rows)->flatten(1)->pluck('command.action')->filter()->all())->toContain('daily_work.complete', 'daily_work', 'home');
    expect($screen->state['dailyWork']['workTypeId'])->toBe($work->id);
    $this->assertDatabaseCount('work_shifts', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('telegram daily work pays once and replays the receipt after catalogue changes', function () {
    $this->freezeSecond();
    $user = User::factory()->create(['coins' => 10, 'gems' => 2]);
    $work = WorkType::factory()->create(['coins_reward' => 35]);
    $menu = app(DailyWorkMenu::class);
    $confirmation = $menu->confirm($user, 'en', ['workTypeId' => $work->id]);

    $menu->complete($user, 'en', $confirmation->state);
    $work->update(['coins_reward' => 100, 'is_active' => false]);
    $replayed = $menu->complete($user, 'en', $confirmation->state);

    expect($replayed->text)->toContain('35');
    expect(collect($replayed->rows)->flatten(1)->pluck('command.action')->filter()->all())->not->toContain('daily_work.confirm', 'daily_work.complete');
    expect($replayed->state)->toBeEmpty();
    $this->assertDatabaseHas('users', ['id' => $user->id, 'coins' => 45, 'gems' => 2]);
    $this->assertDatabaseCount('work_shifts', 1);
    $this->assertDatabaseCount('currency_transactions', 1);
});

test('telegram daily work awards the fifth consecutive day bonus', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-27 12:00:00', 'Europe/Moscow'));
    $user = User::factory()->create(['coins' => 10, 'gems' => 2]);
    $work = WorkType::factory()->create();
    WorkShift::factory()->for($user)->for($work)->create(['worked_on' => '2026-09-26', 'streak_day' => 4]);
    $menu = app(DailyWorkMenu::class);
    $confirmation = $menu->confirm($user, 'en', ['workTypeId' => $work->id]);

    $result = $menu->complete($user, 'en', $confirmation->state);

    expect($confirmation->state['dailyWork']['expectedGems'])->toBe(5);
    expect($result->text)->toContain('5/5');
    $this->assertDatabaseHas('users', ['id' => $user->id, 'coins' => 60, 'gems' => 7]);
    $this->assertDatabaseHas('work_shifts', ['user_id' => $user->id, 'worked_on' => '2026-09-27 00:00:00', 'streak_day' => 5, 'coins_reward' => 50, 'gems_reward' => 5]);
    $this->assertDatabaseCount('currency_transactions', 2);
});

test('telegram daily work rejects a previous game day confirmation after midnight', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-27 23:59:59', 'Europe/Moscow'));
    $user = User::factory()->create(['coins' => 10, 'timezone' => 'Pacific/Honolulu']);
    $work = WorkType::factory()->create();
    $menu = app(DailyWorkMenu::class);
    $confirmation = $menu->confirm($user, 'en', ['workTypeId' => $work->id]);
    $this->travel(1)->seconds();

    expect(fn () => $menu->complete($user, 'en', $confirmation->state))->toThrow(WorkUnavailable::class, 'The daily work details changed. Refresh and confirm again.');

    $this->assertDatabaseCount('work_shifts', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
    $this->assertDatabaseHas('users', ['id' => $user->id, 'coins' => 10]);
});

test('telegram daily work rejects changed reward quotes before collecting', function (array $attributes) {
    $this->travelTo(CarbonImmutable::parse('2026-09-27 12:00:00', 'Europe/Moscow'));
    $user = User::factory()->create(['coins' => 10, 'gems' => 2]);
    $work = WorkType::factory()->create();
    WorkShift::factory()->for($user)->for($work)->create(['worked_on' => '2026-09-26', 'streak_day' => 4]);
    $menu = app(DailyWorkMenu::class);
    $confirmation = $menu->confirm($user, 'en', ['workTypeId' => $work->id]);
    $work->update($attributes);

    expect(fn () => $menu->complete($user, 'en', $confirmation->state))->toThrow(WorkUnavailable::class);

    $this->assertDatabaseCount('work_shifts', 1);
    $this->assertDatabaseCount('currency_transactions', 0);
    $this->assertDatabaseHas('users', ['id' => $user->id, 'coins' => 10, 'gems' => 2]);
})->with(['coins changed' => [['coins_reward' => 100]], 'bonus changed' => [['gems_bonus' => 10]], 'job disabled' => [['is_active' => false]]]);

test('telegram daily work refuses to collect another job after a shift was completed', function () {
    $this->freezeSecond();
    $user = User::factory()->create(['coins' => 10]);
    $work = WorkType::factory()->create();
    $otherWork = WorkType::factory()->create();
    $menu = app(DailyWorkMenu::class);
    $first = $menu->confirm($user, 'en', ['workTypeId' => $work->id]);
    $other = $menu->confirm($user, 'en', ['workTypeId' => $otherWork->id]);
    $menu->complete($user, 'en', $first->state);

    expect(fn () => $menu->complete($user, 'en', $other->state))->toThrow(WorkUnavailable::class);

    $this->assertDatabaseCount('work_shifts', 1);
    $this->assertDatabaseCount('currency_transactions', 1);
    $this->assertDatabaseHas('users', ['id' => $user->id, 'coins' => 60]);
});

test('telegram daily work refuses another players confirmation', function () {
    $this->freezeSecond();
    $owner = User::factory()->create();
    $other = User::factory()->create(['coins' => 10]);
    $work = WorkType::factory()->create();
    $menu = app(DailyWorkMenu::class);
    $confirmation = $menu->confirm($owner, 'en', ['workTypeId' => $work->id]);

    expect(fn () => $menu->complete($other, 'en', $confirmation->state))->toThrow(PetUnavailable::class, 'telegram.errors.stale');

    $this->assertDatabaseCount('work_shifts', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
    $this->assertDatabaseHas('users', ['id' => $other->id, 'coins' => 10]);
});

test('telegram daily work refuses collection after the account is blocked', function () {
    $this->freezeSecond();
    $user = User::factory()->create(['coins' => 10]);
    $work = WorkType::factory()->create();
    $menu = app(DailyWorkMenu::class);
    $confirmation = $menu->confirm($user, 'en', ['workTypeId' => $work->id]);
    $user->forceFill(['status' => PlayerStatus::Blocked])->save();

    expect(fn () => $menu->complete($user, 'en', $confirmation->state))->toThrow(WorkUnavailable::class);

    $screen = $menu->confirm($user, 'en', ['workTypeId' => $work->id]);
    expect($screen->state)->toBeEmpty();
    expect(collect($screen->rows)->flatten(1)->pluck('command.action')->filter()->all())->not->toContain('daily_work.confirm', 'daily_work.complete');
    $this->assertDatabaseCount('work_shifts', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('telegram daily work empty list keeps refresh and home navigation', function () {
    $this->freezeSecond();
    $user = User::factory()->create();

    $screen = app(DailyWorkMenu::class)->index($user, 'en');

    expect($screen->text)->toContain(trans('telegram.daily_work.empty', [], 'en'));
    expect(collect($screen->rows)->flatten(1)->pluck('command.action')->filter()->all())->toContain('daily_work', 'home');
    expect($screen->state)->toBeEmpty();
});

test('telegram daily work paginates available jobs', function () {
    $this->freezeSecond();
    $user = User::factory()->create();
    $jobs = WorkType::factory()->count(7)->create();
    $menu = app(DailyWorkMenu::class);

    $first = $menu->index($user, 'en');
    $last = $menu->index($user, 'en', ['offset' => 6]);

    expect(collect($first->rows)->flatten(1)->pluck('command')->filter()->values()->all())->toContain(['action' => 'daily_work', 'offset' => 6, 'cursor' => null]);
    expect(collect($last->rows)->flatten(1)->pluck('command')->filter()->values()->all())->toContain(['action' => 'daily_work.confirm', 'workTypeId' => $jobs->last()->id]);
    expect(collect($last->rows)->flatten(1)->pluck('command.action')->filter(fn ($action) => $action === 'daily_work.confirm')->count())->toBe(1);
});

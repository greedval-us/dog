<?php

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('new players have empty optional profile and activity fields', function () {
    $user = User::factory()->create()->refresh();

    expect($user->avatar_path)->toBeNull();
    expect($user->bio)->toBeNull();
    expect($user->last_login_at)->toBeNull();
    expect($user->last_seen_at)->toBeNull();
    expect($user->tutorial_completed_at)->toBeNull();
});

test('player activity dates are restored as dates', function () {
    $user = User::factory()->create();
    $user->last_login_at = '2026-09-24 10:00:00';
    $user->last_seen_at = '2026-09-24 10:05:00';
    $user->tutorial_completed_at = '2026-09-24 10:03:00';
    $user->save();
    $user->refresh();

    expect($user->last_login_at)->toBeInstanceOf(CarbonImmutable::class)
        ->format('Y-m-d H:i:s')->toBe('2026-09-24 10:00:00');
    expect($user->last_seen_at)->toBeInstanceOf(CarbonImmutable::class)
        ->format('Y-m-d H:i:s')->toBe('2026-09-24 10:05:00');
    expect($user->tutorial_completed_at)->toBeInstanceOf(CarbonImmutable::class)
        ->format('Y-m-d H:i:s')->toBe('2026-09-24 10:03:00');
});

test('the database rejects duplicate public usernames', function () {
    User::factory()->create(['username' => 'same_player']);

    expect(fn () => User::factory()->create(['username' => 'same_player']))
        ->toThrow(QueryException::class);
});

test('the database requires a public username', function () {
    expect(fn () => User::factory()->create(['username' => null]))
        ->toThrow(QueryException::class);
});

test('player migration preserves existing accounts and assigns distinct public usernames', function () {
    $first = User::factory()->create(['id' => 21, 'name' => 'Same Name']);
    $second = User::factory()->create(['id' => 22, 'name' => 'Same Name']);
    $migration = require database_path('migrations/2026_09_24_112410_add_player_fields_to_users_table.php');
    $migration->down();

    expect(Schema::hasColumn('users', 'username'))->toBeFalse();

    $migration->up();

    $this->assertDatabaseHas('users', [
        'id' => 21, 'name' => 'Same Name', 'email' => $first->email,
        'password' => $first->password, 'username' => 'player_21',
        'status' => 'active', 'coins' => 0, 'gems' => 0,
        'experience' => 0, 'locale' => 'ru', 'timezone' => 'UTC',
    ]);
    $this->assertDatabaseHas('users', [
        'id' => 22, 'email' => $second->email, 'username' => 'player_22',
    ]);
    expect(DB::table('users')->count())->toBe(2);
});

test('statistics migration gives existing players initial values without changing their profile or experience', function () {
    $migration = require database_path('migrations/2026_09_25_103052_add_player_statistics_to_users_table.php');
    $migration->down();
    $user = User::factory()->create(['bio' => 'Люблю собак.', 'experience' => 150]);

    $migration->up();

    $this->assertDatabaseHas('users', [
        'id' => $user->id, 'name' => $user->name, 'username' => $user->username,
        'bio' => 'Люблю собак.', 'experience' => 150,
        'level' => 1, 'exhibition_wins' => 0, 'competition_wins' => 0,
        'walks_count' => 0, 'trainings_count' => 0,
    ]);
});

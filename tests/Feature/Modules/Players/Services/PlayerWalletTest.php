<?php

use App\Models\CurrencyTransaction;
use App\Models\User;
use App\Modules\Players\Exceptions\InsufficientFunds;
use App\Modules\Players\Services\PlayerWallet;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('wallet changes record exact balances and retries return the same operation', function (string $currency, int $amount, int $after) {
    $user = User::factory()->create(['coins' => 100, 'gems' => 100]);
    $wallet = app(PlayerWallet::class);

    $entry = $wallet->change($user, $currency, $amount, 'reward:17', 'test_reward');
    $repeat = $wallet->change($user, $currency, $amount, 'reward:17', 'test_reward');

    expect($repeat->id)->toBe($entry->id);
    $this->assertDatabaseHas('users', ['id' => $user->id, $currency => $after]);
    $this->assertDatabaseHas('currency_transactions', [
        'user_id' => $user->id, 'currency' => $currency, 'amount' => $amount,
        'balance_before' => 100, 'balance_after' => $after, 'operation_key' => 'reward:17',
    ]);
    $this->assertDatabaseCount('currency_transactions', 1);
})->with(['spend coins' => ['coins', -100, 0], 'reward gems' => ['gems', 25, 125]]);

test('a retry returns its original result after other wallet operations', function () {
    $user = User::factory()->create(['coins' => 100]);
    $wallet = app(PlayerWallet::class);
    $entry = $wallet->change($user, 'coins', -50, 'purchase:1', 'purchase');
    $wallet->change($user, 'coins', 20, 'reward:1', 'reward');

    expect($wallet->change($user, 'coins', -50, 'purchase:1', 'purchase')->id)->toBe($entry->id);

    $this->assertDatabaseHas('users', ['id' => $user->id, 'coins' => 70]);
    $this->assertDatabaseCount('currency_transactions', 2);
});

test('insufficient funds leave both balance and ledger unchanged', function () {
    $user = User::factory()->create(['coins' => 99]);

    expect(fn () => app(PlayerWallet::class)->change($user, 'coins', -100, 'purchase:1', 'purchase'))
        ->toThrow(InsufficientFunds::class);

    $this->assertDatabaseHas('users', ['id' => $user->id, 'coins' => 99]);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('reusing an operation key with different contents is rejected', function (string $currency, int $amount, string $reason) {
    $user = User::factory()->create(['coins' => 100, 'gems' => 100]);
    $wallet = app(PlayerWallet::class);
    $wallet->change($user, 'coins', -10, 'purchase:1', 'purchase');

    expect(fn () => $wallet->change($user, $currency, $amount, 'purchase:1', $reason))->toThrow(InvalidArgumentException::class);

    $this->assertDatabaseHas('users', ['id' => $user->id, 'coins' => 90, 'gems' => 100]);
    $this->assertDatabaseCount('currency_transactions', 1);
})->with([
    'different currency' => ['gems', -10, 'purchase'],
    'different amount' => ['coins', -20, 'purchase'],
    'different reason' => ['coins', -10, 'reward'],
]);

test('operation keys are scoped to the player', function () {
    $players = User::factory()->count(2)->create();

    foreach ($players as $player) {
        app(PlayerWallet::class)->change($player, 'coins', 10, 'reward:1', 'reward');
    }

    $this->assertDatabaseCount('currency_transactions', 2);
    foreach ($players as $player) {
        $this->assertDatabaseHas('users', ['id' => $player->id, 'coins' => 10]);
    }
});

test('a failed gameplay transaction rolls back its currency operation', function () {
    $user = User::factory()->create(['coins' => 100]);

    expect(fn () => DB::transaction(function () use ($user): void {
        app(PlayerWallet::class)->change($user, 'coins', -10, 'purchase:1', 'purchase');
        throw new RuntimeException('Gameplay failed.');
    }))->toThrow(RuntimeException::class, 'Gameplay failed.');

    $this->assertDatabaseHas('users', ['id' => $user->id, 'coins' => 100]);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('invalid wallet inputs cannot mutate an account', function (string $currency, int $amount, string $key, string $reason) {
    $user = User::factory()->create(['coins' => 100]);

    expect(fn () => app(PlayerWallet::class)->change($user, $currency, $amount, $key, $reason))->toThrow(InvalidArgumentException::class);

    $this->assertDatabaseHas('users', ['id' => $user->id, 'coins' => 100]);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with([
    'invalid currency' => ['experience', 10, 'reward:1', 'reward'],
    'zero amount' => ['coins', 0, 'reward:1', 'reward'],
    'minimum integer' => ['coins', PHP_INT_MIN, 'reward:1', 'reward'],
    'empty key' => ['coins', 10, '', 'reward'],
    'long key' => ['coins', 10, str_repeat('x', 129), 'reward'],
    'empty reason' => ['coins', 10, 'reward:1', ''],
    'long reason' => ['coins', 10, 'reward:1', str_repeat('x', 65)],
]);

test('wallet balances cannot overflow a signed bigint', function () {
    $user = User::factory()->create(['coins' => PHP_INT_MAX]);

    expect(fn () => app(PlayerWallet::class)->change($user, 'coins', 1, 'reward:1', 'reward'))->toThrow(InvalidArgumentException::class);

    $this->assertDatabaseHas('users', ['id' => $user->id, 'coins' => PHP_INT_MAX]);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('deleting a player retains the currency history without an account reference', function () {
    $user = User::factory()->create(['coins' => 100]);
    $entry = app(PlayerWallet::class)->change($user, 'coins', -10, 'purchase:1', 'purchase');

    $user->delete();

    $this->assertDatabaseHas('currency_transactions', ['id' => $entry->id, 'user_id' => null, 'amount' => -10]);
});

test('a ledger insert failure rolls back the balance update', function () {
    $user = User::factory()->create(['coins' => 100]);
    CurrencyTransaction::creating(function (): void {
        throw new RuntimeException('Ledger unavailable.');
    });

    try {
        expect(fn () => app(PlayerWallet::class)->change($user, 'coins', -10, 'purchase:1', 'purchase'))
            ->toThrow(RuntimeException::class, 'Ledger unavailable.');
    } finally {
        CurrencyTransaction::flushEventListeners();
    }

    $this->assertDatabaseHas('users', ['id' => $user->id, 'coins' => 100]);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('PostgreSQL refuses a negative balance even when the wallet service is bypassed', function (string $currency) {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL check constraint.');
    }
    $user = User::factory()->create();

    expect(fn () => DB::transaction(fn () => User::query()->whereKey($user->id)->update([$currency => -1])))
        ->toThrow(QueryException::class);

    $this->assertDatabaseHas('users', ['id' => $user->id, $currency => 0]);
})->with(['coins', 'gems']);

test('PostgreSQL rejects inconsistent ledger entries', function (array $attributes) {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL check constraint.');
    }
    $user = User::factory()->create();

    expect(fn () => DB::transaction(fn () => CurrencyTransaction::factory()->for($user)->create($attributes)))
        ->toThrow(QueryException::class);

    $this->assertDatabaseCount('currency_transactions', 0);
})->with([
    'wrong balance' => [['balance_after' => 99]],
    'unknown currency' => [['currency' => 'experience']],
    'negative balance' => [['balance_before' => -100, 'balance_after' => 0]],
    'empty operation' => [['amount' => 0]],
]);

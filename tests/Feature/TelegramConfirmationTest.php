<?php

use App\Http\Telegram\GameMenu;
use App\Models\User;
use App\Modules\Pets\Exceptions\PetUnavailable;

test('telegram malformed confirmations become stale screens before gameplay runs', function (string $action, array $state) {
    $user = User::factory()->create();

    expect(fn () => app(GameMenu::class)->render($user, ['action' => $action], $state))
        ->toThrow(PetUnavailable::class, 'telegram.errors.stale');

    foreach (['pet_care_actions', 'pet_skill_lessons', 'dog_work_shifts', 'veterinary_visits', 'item_purchases', 'currency_transactions'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
})->with([
    'missing care draft' => ['care.start', []],
    'invalid care items' => ['care.start', ['draft' => ['petId' => 1, 'variant' => 'water', 'itemIds' => ['food' => []], 'skipped' => [], 'token' => '00000000-0000-4000-8000-000000000000']]],
    'invalid care skipped categories' => ['care.start', ['draft' => ['petId' => 1, 'variant' => 'water', 'itemIds' => [], 'skipped' => [false], 'token' => '00000000-0000-4000-8000-000000000000']]],
    'missing shop quote' => ['shop.buy', []],
    'invalid shop quote field' => ['shop.confirm', ['purchase' => ['offerId' => [], 'itemId' => 1, 'expectedPrice' => 0, 'expectedCurrency' => 'coins', 'name' => 'Free item']]],
    'missing lesson quote' => ['skills.train', []],
    'invalid lesson identifier' => ['skills.train', ['skillLesson' => ['petId' => [], 'skillId' => 1, 'level' => 1, 'expectedPrice' => 100, 'token' => '00000000-0000-4000-8000-000000000000', 'name' => 'Skill']]],
    'invalid lesson token' => ['skills.train', ['skillLesson' => ['petId' => 1, 'skillId' => 1, 'level' => 1, 'expectedPrice' => 100, 'token' => false, 'name' => 'Skill']]],
    'missing work draft' => ['work.start', []],
    'missing veterinary quote' => ['clinic.perform', []],
    'invalid veterinary service' => ['clinic.perform', ['veterinary' => ['petId' => 1, 'service' => 'unknown', 'episodeId' => null, 'expectedPrice' => 60, 'token' => '00000000-0000-4000-8000-000000000000']]],
    'invalid veterinary episode' => ['clinic.perform', ['veterinary' => ['petId' => 1, 'service' => 'treatment', 'episodeId' => [], 'expectedPrice' => 120, 'token' => '00000000-0000-4000-8000-000000000000']]],
]);

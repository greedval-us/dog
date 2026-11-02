<?php

use App\Http\Telegram\GameMenu;
use App\Http\Telegram\MenuMessenger;
use App\Models\Dog;
use App\Models\Pet;
use App\Models\TelegramAccount;
use App\Models\User;
use App\Modules\Kennel\Exceptions\AdoptionUnavailable;
use App\Modules\Kennel\Exceptions\StarterBreedUnavailable;
use App\Modules\Kennel\Exceptions\StarterPetAlreadyClaimed;
use App\Modules\Pets\Exceptions\PetUnavailable;
use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Models\TelegraphBot;
use DefStudio\Telegraph\Models\TelegraphChat;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/** @return array<string, mixed> */
function telegramKennelConfirmation(User $user, Dog $breed, int $months = 3): array
{
    $menu = app(GameMenu::class);
    $prompt = $menu->render($user, ['action' => 'kennel.prepare', 'dogId' => $breed->id, 'ageMonths' => $months]);

    return $menu->render($user, ['action' => 'kennel.name', 'text' => '  Рэй <друг>  '], $prompt->state)->state;
}

test('telegram kennel exposes available breeds and free adoption without charging on name entry', function () {
    $this->freezeSecond();
    $owner = User::factory()->create(['locale' => 'en', 'coins' => 37]);
    $breed = Dog::factory()->create(['is_starter' => true, 'name' => ['en' => '<Breed>', 'ru' => 'Порода']]);
    Dog::factory()->create(['is_starter' => false]);
    $menu = app(GameMenu::class);

    $index = $menu->render($owner, ['action' => 'kennel']);
    $state = telegramKennelConfirmation($owner, $breed, 18);
    $review = $menu->render($owner, ['action' => 'kennel.confirm'], $state);

    expect($index->rows[0][0]['command'])->toBe(['action' => 'kennel.breed', 'dogId' => $breed->id]);
    expect($state['kennel'])->toMatchArray(['userId' => $owner->id, 'name' => 'Рэй <друг>', 'mode' => 'starter', 'expectedPrice' => 0, 'ageMonths' => 18, 'awaitingName' => false]);
    expect($review->text)->toContain('&lt;Breed&gt;', 'Рэй &lt;друг&gt;')->not->toContain('<друг>');
    expect($review->state['kennel']['token'])->toBe($state['kennel']['token']);
    expect(Str::isUuid($state['kennel']['token']))->toBeTrue();
    expect($owner->fresh()->coins)->toBe(37);
    $this->assertDatabaseCount('pets', 0);
});

test('telegram free adoption keeps a receipt until menu delivery succeeds', function (int $months) {
    $this->freezeSecond();
    $owner = User::factory()->create(['coins' => 37]);
    $account = TelegramAccount::factory()->for($owner)->create();
    $breed = Dog::factory()->create(['is_starter' => true]);
    $state = telegramKennelConfirmation($owner, $breed, $months);
    $menu = app(GameMenu::class);

    $menu->render($owner, ['action' => 'kennel.adopt'], $state);
    $pet = $owner->pets()->sole();
    $pet->forceFill(['died_at' => now(), 'health' => 0])->save();
    $replayed = $menu->render($owner, ['action' => 'kennel.adopt'], $state);

    $this->assertDatabaseCount('pets', 1);
    expect($pet->born_at->equalTo(now()->subDays($months)))->toBeTrue();
    expect($account->fresh()->menu_state)->toHaveKey('kennelAdoption');
    expect($owner->fresh()->coins)->toBe(37);
    expect(collect($replayed->rows)->flatten(1)->pluck('command.action')->all())->not->toContain('pet');
})->with([3, 18]);

test('telegram kennel uses the paid native receipt and confirmed price and age on retry', function () {
    $this->freezeSecond();
    $owner = User::factory()->create(['coins' => 2000, 'pet_slots' => 2]);
    Pet::factory()->for($owner)->create();
    $breed = Dog::factory()->create(['is_starter' => true]);
    $state = telegramKennelConfirmation($owner, $breed, 18);
    $menu = app(GameMenu::class);

    $menu->render($owner, ['action' => 'kennel.adopt'], $state);
    config(['doglive.kennel_price' => 900]);
    $menu->render($owner, ['action' => 'kennel.adopt'], $state);

    expect($owner->fresh()->coins)->toBe(1500);
    $this->assertDatabaseCount('pets', 2);
    $this->assertDatabaseCount('kennel_purchases', 1);
    $this->assertDatabaseHas('kennel_purchases', ['user_id' => $owner->id, 'token' => $state['kennel']['token'], 'price_paid' => 500, 'age_months' => 18, 'pet_name' => 'Рэй <друг>']);
});

test('telegram kennel refuses changed availability without switching the confirmed purchase mode', function (string $change) {
    $owner = User::factory()->create(['coins' => 2000, 'pet_slots' => 2]);
    Pet::factory()->for($owner)->create();
    $breed = Dog::factory()->create(['is_starter' => true]);
    $state = telegramKennelConfirmation($owner, $breed);
    match ($change) {
        'price' => config(['doglive.kennel_price' => 600]),
        'funds' => $owner->forceFill(['coins' => 100])->save(),
        'slots' => $owner->forceFill(['pet_slots' => 1])->save(),
        'breed' => $breed->update(['is_starter' => false]),
    };

    expect(fn () => app(GameMenu::class)->render($owner, ['action' => 'kennel.adopt'], $state))
        ->toThrow($change === 'breed' ? StarterBreedUnavailable::class : AdoptionUnavailable::class);

    $this->assertDatabaseCount('pets', 1);
    $this->assertDatabaseCount('kennel_purchases', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with(['price', 'funds', 'slots', 'breed']);

test('telegram kennel rejects invalid names and drafts belonging to another player', function () {
    $owner = User::factory()->create();
    $breed = Dog::factory()->create(['is_starter' => true]);
    $menu = app(GameMenu::class);
    $prompt = $menu->render($owner, ['action' => 'kennel.prepare', 'dogId' => $breed->id, 'ageMonths' => 3]);

    foreach (['  ', str_repeat('я', 65), "bad\nname"] as $name) {
        $result = $menu->render($owner, ['action' => 'kennel.name', 'text' => $name], $prompt->state);
        expect($result->state['kennel']['awaitingName'])->toBeTrue();
        expect(collect($result->rows)->flatten(1)->pluck('command.action')->all())->not->toContain('kennel.adopt');
    }
    expect(fn () => $menu->render(User::factory()->create(), ['action' => 'kennel.name', 'text' => 'Рэй'], $prompt->state))->toThrow(PetUnavailable::class);
    $this->assertDatabaseCount('pets', 0);
});

test('telegram kennel and capacity remain reachable when a player has no dogs or no free places', function () {
    $owner = User::factory()->create();
    $menu = app(GameMenu::class);
    $empty = $menu->render($owner, ['action' => 'pets']);
    Pet::factory()->for($owner)->create();
    Dog::factory()->create(['is_starter' => true]);

    $full = $menu->render($owner, ['action' => 'kennel']);

    expect(collect($empty->rows)->flatten(1)->pluck('command.action')->all())->toContain('kennel', 'slots');
    expect(collect($full->rows)->flatten(1)->pluck('command.action')->all())->toContain('slots')->not->toContain('kennel.prepare');
});

test('a free telegram confirmation never switches to a paid purchase when another dog arrives', function () {
    $owner = User::factory()->create(['coins' => 1000, 'pet_slots' => 2]);
    TelegramAccount::factory()->for($owner)->create();
    $breed = Dog::factory()->create(['is_starter' => true]);
    $state = telegramKennelConfirmation($owner, $breed);
    Pet::factory()->for($owner)->create();

    expect(fn () => app(GameMenu::class)->render($owner, ['action' => 'kennel.adopt'], $state))->toThrow(StarterPetAlreadyClaimed::class);

    expect($owner->fresh()->coins)->toBe(1000);
    $this->assertDatabaseCount('pets', 1);
    $this->assertDatabaseCount('kennel_purchases', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('editing a confirmed name creates a new purchase token only when the name changes', function (string $name, bool $changed) {
    $owner = User::factory()->create();
    $breed = Dog::factory()->create(['is_starter' => true]);
    $menu = app(GameMenu::class);
    $state = telegramKennelConfirmation($owner, $breed);
    $prompt = $menu->render($owner, ['action' => 'kennel.rename'], $state);

    $confirmation = $menu->render($owner, ['action' => 'kennel.name', 'text' => $name], $prompt->state);

    expect($confirmation->state['kennel']['token'] !== $state['kennel']['token'])->toBe($changed);
    expect($confirmation->state['kennel']['name'])->toBe($name);
    $this->assertDatabaseCount('pets', 0);
})->with(['unchanged' => ['Рэй <друг>', false], 'changed' => ['Дружок', true]]);

test('telegram confirmed kennel and slot operations recover from delivery failure without a duplicate purchase', function (string $kind, int $balance, int $pets, int $debits) {
    $this->freezeSecond();
    config(['telegraph.webhook.secret' => 'test-secret']);
    Http::preventStrayRequests();
    $owner = User::factory()->create(['coins' => 1000, 'pet_slots' => 2, 'locale' => 'en']);
    $bot = TelegraphBot::factory()->create();
    $chat = TelegraphChat::factory()->for($bot, 'bot')->create(['chat_id' => '123456']);
    $account = TelegramAccount::factory()->for($owner)->create(['telegraph_chat_id' => $chat->id]);
    if ($kind === 'purchase') {
        Pet::factory()->for($owner)->create();
    }
    $menu = app(GameMenu::class);
    if ($kind === 'slots') {
        $confirmation = $menu->render($owner, ['action' => 'slots.confirm', 'slot' => 3, 'currency' => 'coins']);
        $action = 'slots.buy';
    } else {
        $breed = Dog::factory()->create(['is_starter' => true]);
        $confirmation = $menu->render($owner, ['action' => 'kennel.confirm'], telegramKennelConfirmation($owner, $breed));
        $action = 'kennel.adopt';
    }
    Telegraph::fake(['sendMessage' => ['ok' => true, 'result' => ['message_id' => 900]]]);
    app(MenuMessenger::class)->publish($account, $confirmation);
    $key = array_search($action, array_column($account->menu_state['commands'], 'action'), true);
    $update = ['callback_query' => ['id' => 'request', 'from' => ['id' => 123456],
        'message' => ['message_id' => 900, 'chat' => ['id' => 123456, 'type' => 'private']],
        'data' => 'action:tap;k:'.$key.';v:'.$account->menu_revision.';s:'.$account->id]];
    $headers = ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'];
    Telegraph::fake(['answerCallbackQuery' => ['ok' => true, 'result' => true],
        'sendMessage' => ['ok' => false, 'error_code' => 429, 'parameters' => ['retry_after' => 5]]]);

    $this->postJson(route('telegraph.webhook', $bot->token), $update, $headers)->assertStatus(500);
    $this->assertDatabaseHas('telegram_accounts', ['id' => $account->id, 'menu_message_id' => 900, 'menu_revision' => 1]);
    Telegraph::assertNotSentData('deleteMessage', ['message_id' => 900]);
    if ($kind === 'starter') {
        $owner->pets()->sole()->forceFill(['surrendered_at' => now()])->save();
    }
    Telegraph::fake(['answerCallbackQuery' => ['ok' => true, 'result' => true],
        'sendMessage' => ['ok' => true, 'result' => ['message_id' => 901]],
        'deleteMessage' => ['ok' => true, 'result' => true]]);
    $this->postJson(route('telegraph.webhook', $bot->token), $update, $headers)->assertNoContent();

    expect($owner->fresh()->coins)->toBe($balance);
    $this->assertDatabaseCount('pets', $pets);
    $this->assertDatabaseCount('currency_transactions', $debits);
    expect($account->fresh()->menu_state)->not->toHaveKey('kennelAdoption');
    $this->assertDatabaseHas('telegram_accounts', ['id' => $account->id, 'menu_message_id' => 901, 'menu_revision' => 2]);
    Telegraph::assertSentData('sendMessage', ['chat_id' => '123456', 'disable_notification' => true]);
    Telegraph::assertSentData('deleteMessage', ['message_id' => 900]);
})->with(['starter' => ['starter', 1000, 1, 0], 'purchase' => ['purchase', 500, 2, 1], 'slots' => ['slots', 800, 0, 1]]);

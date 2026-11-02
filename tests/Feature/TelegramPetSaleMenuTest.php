<?php

use App\Http\Telegram\HandleTelegramUpdate;
use App\Http\Telegram\MenuMessenger;
use App\Http\Telegram\Menus\PetSaleMenu;
use App\Models\Dog;
use App\Models\Pet;
use App\Models\PetSaleListing;
use App\Models\TelegramAccount;
use App\Models\User;
use App\Modules\Pets\Actions\PublishPetSale;
use App\Modules\Pets\Enums\PetActivity;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Telegram\DTO\TelegramUpdate;
use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Models\TelegraphBot;
use DefStudio\Telegraph\Models\TelegraphChat;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/** @return array<string,mixed> */
function telegramPetSaleDraft(User $owner, Pet $pet, string $price = '250'): array
{
    $menu = app(PetSaleMenu::class);
    $prompt = $menu->prompt($owner, 'en', ['petId' => $pet->id]);

    return $menu->price($owner, 'en', ['text' => $price], $prompt->state)->state;
}

test('telegram selling lists six active dogs belonging to the current owner', function () {
    $owner = User::factory()->create();
    $breed = Dog::factory()->create();
    $pets = Pet::factory()->for($owner)->for($breed)->count(7)->create();
    Pet::factory()->for($owner)->deceased()->create();
    Pet::factory()->create(['name' => 'Other owner']);
    $menu = app(PetSaleMenu::class);

    $first = $menu->choose($owner, 'en', []);
    $second = $menu->choose($owner, 'en', ['offset' => 6]);

    $choices = collect($first->rows)->flatten(1)->where('command.action', 'market.sell.pet');
    expect($choices)->toHaveCount(6);
    expect($choices->pluck('command.petId')->all())->toBe($pets->take(6)->pluck('id')->all());
    expect(collect($second->rows)->flatten(1)->where('command.action', 'market.sell.pet')->pluck('command.petId')->all())->toBe([$pets->last()->id]);
});

test('telegram price entry escapes names and only prepares a confirmation', function (string $locale) {
    $this->freezeSecond();
    $owner = User::factory()->create(['coins' => 0]);
    $breed = Dog::factory()->create(['name' => ['en' => '<Breed>', 'ru' => '<Порода>']]);
    $pet = Pet::factory()->for($owner)->for($breed)->create(['name' => 'Рэй <друг>']);
    $menu = app(PetSaleMenu::class);
    $prompt = $menu->prompt($owner, $locale, ['petId' => $pet->id]);

    $confirmation = $menu->price($owner, $locale, ['text' => ' 250 '], $prompt->state);

    expect($confirmation->text)->toContain('Рэй &lt;друг&gt;', $locale === 'en' ? '&lt;Breed&gt;' : '&lt;Порода&gt;', '250')->not->toContain('<друг>');
    expect($confirmation->state['marketSale'])->toMatchArray(['userId' => $owner->id, 'petId' => $pet->id, 'price' => 250, 'awaitingPrice' => false]);
    expect(Str::isUuid($confirmation->state['marketSale']['token']))->toBeTrue();
    expect(collect($confirmation->rows)->flatten(1)->pluck('command.action')->all())->toContain('market.sell.publish', 'market.sell.price');
    expect($owner->fresh()->coins)->toBe(0);
    $this->assertDatabaseCount('pet_sale_listings', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with(['en', 'ru']);

test('invalid telegram sale prices remain in the input step', function (string $price) {
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();
    $menu = app(PetSaleMenu::class);
    $prompt = $menu->prompt($owner, 'en', ['petId' => $pet->id]);

    $result = $menu->price($owner, 'en', ['text' => $price], $prompt->state);

    expect($result->state['marketSale'])->price->toBeNull()->awaitingPrice->toBeTrue();
    expect($result->text)->toContain('1', '1000000');
    expect(collect($result->rows)->flatten(1)->pluck('command.action')->all())->not->toContain('market.sell.publish');
    $this->assertDatabaseCount('pet_sale_listings', 0);
})->with(['empty' => '', 'zero' => '0', 'negative' => '-1', 'decimal' => '1.5', 'exponent' => '1e3', 'above maximum' => '1000001', 'overflow' => str_repeat('9', 40), 'HTML' => '<b>25</b>']);

test('sale publication needs confirmed owned state and repeats native eligibility checks', function (string $change) {
    $this->freezeSecond();
    $owner = User::factory()->create();
    TelegramAccount::factory()->for($owner)->create();
    $pet = Pet::factory()->for($owner)->create();
    $state = telegramPetSaleDraft($owner, $pet);
    match ($change) {
        'owner' => $state['marketSale']['userId'] = $owner->id + 1,
        'unfinished' => $state['marketSale']['awaitingPrice'] = true,
        'price type' => $state['marketSale']['price'] = '250',
        'above maximum' => $state['marketSale']['price'] = 1000001,
        'busy' => $pet->forceFill(['activity' => PetActivity::Walk, 'activity_ends_at' => now()->addHour()])->save(),
    };

    expect(fn () => app(PetSaleMenu::class)->publish($owner, 'en', $state))->toThrow(PetUnavailable::class);

    $this->assertDatabaseCount('pet_sale_listings', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with(['owner', 'unfinished', 'price type', 'above maximum', 'busy']);

test('seller detail and input cannot inspect a different owners dog', function (string $method) {
    $owner = User::factory()->create();
    $pet = Pet::factory()->create();

    expect(fn () => app(PetSaleMenu::class)->{$method}($owner, 'en', ['petId' => $pet->id]))->toThrow(ModelNotFoundException::class);
})->with(['show', 'prompt', 'withdrawConfirmation']);

test('telegram listing can be withdrawn while its dog is busy', function () {
    $this->freezeSecond();
    $owner = User::factory()->create(['coins' => 0]);
    TelegramAccount::factory()->for($owner)->create();
    $pet = Pet::factory()->for($owner)->create(['activity' => PetActivity::Walk, 'activity_ends_at' => now()->addHour()]);
    $listing = PetSaleListing::factory()->for($pet)->for($owner)->create(['price' => 75]);
    $menu = app(PetSaleMenu::class);

    $detail = $menu->show($owner, 'en', ['petId' => $pet->id]);
    $confirmation = $menu->withdrawConfirmation($owner, 'en', ['petId' => $pet->id]);

    expect(collect($detail->rows)->flatten(1)->pluck('command.action')->all())->toContain('market.withdraw-confirm')->not->toContain('market.sell.price');
    expect($listing->fresh()->is_active)->toBeTrue();
    $menu->withdraw($owner, 'en', $confirmation->state);
    expect($listing->fresh()->is_active)->toBeFalse();
    expect($pet->fresh()->activity)->toBe(PetActivity::Walk);
    expect($owner->fresh()->coins)->toBe(0);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('a sale prompt opened after the dog becomes busy shows its localized restriction', function () {
    $this->freezeSecond();
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create(['activity' => PetActivity::Walk, 'activity_ends_at' => now()->addHour()]);

    $result = app(PetSaleMenu::class)->prompt($owner, 'ru', ['petId' => $pet->id]);

    expect($result->text)->toContain(trans('ownership.errors.busy', [], 'ru'));
    expect($result->state)->not->toHaveKey('marketSale');
    expect(collect($result->rows)->flatten(1)->pluck('command.action')->all())->not->toContain('market.sell.publish');
    $this->assertDatabaseCount('pet_sale_listings', 0);
});

test('telegram sale delivery retries retain the original mutation without changing a newer website listing', function (string $kind) {
    $this->freezeSecond();
    config(['telegraph.webhook.secret' => 'test-secret']);
    Http::preventStrayRequests();
    Http::fake(['api.telegram.org/bot*/getMe' => Http::response(['ok' => true, 'result' => ['username' => 'doglive_test_bot']])]);
    $bot = TelegraphBot::factory()->create();
    $chat = TelegraphChat::factory()->for($bot, 'bot')->create(['chat_id' => '123456']);
    $owner = User::factory()->create(['locale' => 'en', 'coins' => 0]);
    $account = TelegramAccount::factory()->for($owner)->for($chat, 'chat')->create();
    $pet = Pet::factory()->for($owner)->create();
    $menu = app(PetSaleMenu::class);
    $screen = $menu->price($owner, 'en', ['text' => '250'], $menu->prompt($owner, 'en', ['petId' => $pet->id])->state);
    $action = 'market.sell.publish';
    if ($kind === 'withdraw') {
        app(PublishPetSale::class)->handle($owner, $pet->id, 250);
        $screen = $menu->withdrawConfirmation($owner, 'en', ['petId' => $pet->id]);
        $action = 'market.withdraw';
    }
    Telegraph::fake(['sendMessage' => ['ok' => true, 'result' => ['message_id' => 900]]]);
    app(MenuMessenger::class)->publish($account, $screen);
    $key = array_search($action, array_column($account->menu_state['commands'], 'action'), true);
    $update = ['callback_query' => ['id' => 'sale-request', 'from' => ['id' => 123456],
        'message' => ['message_id' => 900, 'chat' => ['id' => 123456, 'type' => 'private']],
        'data' => 'action:tap;k:'.$key.';v:'.$account->menu_revision.';s:'.$account->id]];
    $headers = ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'];
    Telegraph::fake(['answerCallbackQuery' => ['ok' => true, 'result' => true],
        'sendMessage' => ['ok' => false, 'error_code' => 429, 'parameters' => ['retry_after' => 5]]]);

    $this->postJson(route('telegraph.webhook', $bot->token), $update, $headers)->assertStatus(500);

    expect($account->fresh()->menu_state)->toHaveKey('marketMutation');
    expect(PetSaleListing::query()->sole()->is_active)->toBe($kind === 'publish');
    if ($kind === 'publish') {
        app(PublishPetSale::class)->withdraw($owner, $pet->id);
    } else {
        app(PublishPetSale::class)->handle($owner, $pet->id, 500);
    }
    Telegraph::fake(['answerCallbackQuery' => ['ok' => true, 'result' => true],
        'sendMessage' => ['ok' => true, 'result' => ['message_id' => 901]],
        'deleteMessage' => ['ok' => true, 'result' => true]]);

    $this->postJson(route('telegraph.webhook', $bot->token), $update, $headers)->assertNoContent();

    expect(PetSaleListing::query()->sole()->is_active)->toBe($kind === 'withdraw');
    expect(PetSaleListing::query()->sole()->price)->toBe($kind === 'withdraw' ? 500 : 250);
    expect($account->fresh()->menu_state)->not->toHaveKey('marketMutation');
    expect($owner->fresh()->coins)->toBe(0);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with(['publish', 'withdraw']);

test('sale price text needs the current owner an open prompt and a fresh message', function (array $changes, ?int $delay) {
    $this->freezeSecond();
    Http::preventStrayRequests();
    Http::fake(['api.telegram.org/bot*/getMe' => Http::response(['ok' => true, 'result' => ['username' => 'doglive_test_bot']])]);
    $bot = TelegraphBot::factory()->create();
    $chat = TelegraphChat::factory()->for($bot, 'bot')->create(['chat_id' => '123456']);
    $account = TelegramAccount::factory()->for($chat, 'chat')->create();
    $draft = array_replace(['userId' => $account->user_id, 'petId' => 1, 'price' => null,
        'token' => '00000000-0000-4000-8000-000000000001', 'awaitingPrice' => true, 'priceRequestedAt' => now()->timestamp], $changes);
    $state = ['commands' => [], 'data' => ['marketSale' => $draft]];
    $account->forceFill(['menu_revision' => 2, 'menu_message_id' => 900, 'menu_state' => $state])->save();
    Telegraph::fake();

    app(HandleTelegramUpdate::class)->handle($bot, new TelegramUpdate('123456', 10, '', null,
        text: '250', sentAt: $delay === null ? null : now()->timestamp + $delay));

    Telegraph::assertNothingSent();
    expect($account->fresh())->menu_revision->toBe(2)->menu_state->toBe($state);
    $this->assertDatabaseCount('pet_sale_listings', 0);
})->with([
    'other owner' => [['userId' => -1], 0], 'closed prompt' => [['awaitingPrice' => false], 0],
    'non boolean flag' => [['awaitingPrice' => 1], 0], 'missing timestamp' => [['priceRequestedAt' => null], 0],
    'timestamp type' => [['priceRequestedAt' => '1'], 0], 'zero timestamp' => [['priceRequestedAt' => 0], 0],
    'missing message timestamp' => [[], null], 'old text' => [[], -1],
]);

test('current price text prepares confirmation and menu commands leave the sale input', function (?string $command) {
    $this->freezeSecond();
    Http::preventStrayRequests();
    Http::fake(['api.telegram.org/bot*/getMe' => Http::response(['ok' => true, 'result' => ['username' => 'doglive_test_bot']])]);
    $bot = TelegraphBot::factory()->create();
    $chat = TelegraphChat::factory()->for($bot, 'bot')->create(['chat_id' => '123456']);
    $account = TelegramAccount::factory()->for($chat, 'chat')->create();
    $pet = Pet::factory()->for($account->user)->create();
    $prompt = app(PetSaleMenu::class)->prompt($account->user, 'en', ['petId' => $pet->id]);
    $account->forceFill(['menu_revision' => 2, 'menu_message_id' => 900, 'menu_state' => ['commands' => [], 'data' => $prompt->state]])->save();
    Telegraph::fake(['sendMessage' => ['ok' => true, 'result' => ['message_id' => 901]], 'deleteMessage' => ['ok' => true, 'result' => true]]);

    app(HandleTelegramUpdate::class)->handle($bot, new TelegramUpdate('123456', 10, '', null,
        command: $command, text: $command === null ? '250' : null, sentAt: now()->timestamp));

    $data = $account->fresh()->menu_state['data'];
    if ($command === null) {
        expect($data['marketSale'])->price->toBe(250)->awaitingPrice->toBeFalse();
    } else {
        expect($data)->not->toHaveKey('marketSale');
    }
    Telegraph::assertSentData('deleteMessage', ['message_id' => 10]);
    $this->assertDatabaseCount('pet_sale_listings', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with(['price' => null, 'menu' => 'menu', 'help' => 'help']);

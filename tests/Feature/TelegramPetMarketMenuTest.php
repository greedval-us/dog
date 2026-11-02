<?php

use App\Http\Telegram\GameMenu;
use App\Http\Telegram\MenuMessenger;
use App\Http\Telegram\Menus\PetMarketMenu;
use App\Models\Dog;
use App\Models\Pet;
use App\Models\PetSaleListing;
use App\Models\TelegramAccount;
use App\Models\User;
use App\Modules\Pets\Enums\PetSex;
use App\Modules\Telegram\DTO\TelegramScreen;
use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Models\TelegraphBot;
use DefStudio\Telegraph\Models\TelegraphChat;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/** @return list<array<string,mixed>> */
function telegramMarketCommands(TelegramScreen $screen): array
{
    return collect($screen->rows)->flatten(1)->pluck('command')->filter()->values()->all();
}

test('telegram market traverses every native cursor page and returns to its previous visible slice', function () {
    $this->freezeSecond();
    $owner = User::factory()->create();
    $seller = User::factory()->create();
    $listings = collect();
    for ($i = 0; $i < 15; $i++) {
        $pet = Pet::factory()->for($seller)->create(['name' => 'Dog '.$i]);
        $listings->push(PetSaleListing::factory()->for($seller, 'user')->for($pet)->create(['price' => 100]));
    }
    $menu = app(PetMarketMenu::class);

    $first = $menu->index($owner, 'en', []);
    $secondCommand = collect(telegramMarketCommands($first))->firstWhere('offset', 6);
    $second = $menu->index($owner, 'en', $secondCommand);
    $thirdCommand = collect(telegramMarketCommands($second))->first(fn (array $command): bool => $command['action'] === 'market' && ($command['cursor'] ?? null) !== null);
    $third = $menu->index($owner, 'en', $thirdCommand);
    $previous = collect(telegramMarketCommands($third))->first(fn (array $command): bool => $command['action'] === 'market' && ($command['cursor'] ?? null) !== null);
    $back = $menu->index($owner, 'en', $previous);

    expect(array_column(array_filter(telegramMarketCommands($first), fn (array $c): bool => $c['action'] === 'market.show'), 'listingId'))->toBe($listings->take(6)->pluck('id')->all());
    expect(array_column(array_filter(telegramMarketCommands($third), fn (array $c): bool => $c['action'] === 'market.show'), 'listingId'))->toBe($listings->slice(12)->pluck('id')->all());
    expect(array_column(array_filter(telegramMarketCommands($back), fn (array $c): bool => $c['action'] === 'market.show'), 'listingId'))->toBe($listings->slice(6, 6)->pluck('id')->all());
});

test('telegram market cards keep public dog details escaped and attach the breed image', function () {
    $this->freezeSecond();
    Storage::fake('local');
    $image = 'breeds/german_shepherd/portrait.png';
    Storage::disk('local')->put($image, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a5XcAAAAASUVORK5CYII='));
    $viewer = User::factory()->create();
    $seller = User::factory()->create();
    $breed = Dog::factory()->create(['breed' => 'german_shepherd', 'name' => ['en' => '<Shepherd>']]);
    $pet = Pet::factory()->for($seller)->for($breed)->create(['name' => '<Rex>', 'born_at' => now()->subDays(18)]);
    $listing = PetSaleListing::factory()->for($seller, 'user')->for($pet)->create(['price' => 123]);

    $screen = app(PetMarketMenu::class)->show($viewer, 'en', ['listingId' => $listing->id, 'sex' => null, 'offset' => 6]);

    expect($screen->text)->toContain('&lt;Rex&gt;', '&lt;Shepherd&gt;', '123', '18', '@'.$seller->username)->not->toContain('<Rex>', $seller->email);
    expect($screen->photoPath)->toBe(realpath(Storage::disk('local')->path($image)));
    expect(array_column(telegramMarketCommands($screen), 'action'))->toContain('market.confirm', 'market');
    expect(collect(telegramMarketCommands($screen))->firstWhere('action', 'market')['offset'])->toBe(6);
});

test('telegram market switches between own listings and sex filtered offers without exposing a self purchase', function () {
    $viewer = User::factory()->create();
    $seller = User::factory()->create();
    $male = Pet::factory()->for($seller)->create(['sex' => PetSex::Male]);
    $female = Pet::factory()->for($seller)->create(['sex' => PetSex::Female]);
    $own = Pet::factory()->for($viewer)->create(['sex' => PetSex::Male]);
    $maleListing = PetSaleListing::factory()->for($seller, 'user')->for($male)->create();
    PetSaleListing::factory()->for($seller, 'user')->for($female)->create();
    $ownListing = PetSaleListing::factory()->for($viewer, 'user')->for($own)->create();
    $menu = app(PetMarketMenu::class);

    $filtered = $menu->index($viewer, 'en', ['sex' => 'male']);
    $mine = $menu->index($viewer, 'en', ['tab' => 'own']);
    $detail = $menu->show($viewer, 'en', ['listingId' => $ownListing->id, 'tab' => 'own']);

    expect(array_column(array_filter(telegramMarketCommands($filtered), fn (array $c): bool => $c['action'] === 'market.show'), 'listingId'))->toBe([$maleListing->id]);
    expect(array_column(array_filter(telegramMarketCommands($mine), fn (array $c): bool => $c['action'] === 'market.show'), 'listingId'))->toBe([$ownListing->id]);
    expect(array_column(telegramMarketCommands($detail), 'action'))->toContain('market.sell.pet', 'market.withdraw-confirm')->not->toContain('market.confirm');
});

test('telegram market purchases retain native owner lock ordering and recover delivery without duplicate transfers', function (bool $deliveryFails) {
    $this->freezeSecond();
    config(['telegraph.webhook.secret' => 'market-secret']);
    Http::preventStrayRequests();
    $seller = User::factory()->create(['coins' => 20]);
    $buyer = User::factory()->create(['coins' => 500]);
    $pet = Pet::factory()->for($seller)->create();
    $listing = PetSaleListing::factory()->for($seller, 'user')->for($pet)->create(['price' => 137]);
    $bot = TelegraphBot::factory()->create();
    $chat = TelegraphChat::factory()->for($bot, 'bot')->create(['chat_id' => '123456']);
    $account = TelegramAccount::factory()->for($buyer)->create(['telegraph_chat_id' => $chat->id]);
    $menu = app(GameMenu::class);
    $confirmation = $menu->render($buyer, ['action' => 'market.confirm', 'listingId' => $listing->id]);
    Telegraph::fake(['sendMessage' => ['ok' => true, 'result' => ['message_id' => 901]]]);
    app(MenuMessenger::class)->publish($account, $confirmation);
    $key = array_search('market.buy', array_column($account->menu_state['commands'], 'action'), true);
    $update = ['callback_query' => ['id' => 'purchase', 'from' => ['id' => 123456],
        'message' => ['message_id' => 901, 'chat' => ['id' => 123456, 'type' => 'private']],
        'data' => 'action:tap;k:'.$key.';v:'.$account->menu_revision.';s:'.$account->id]];
    $baseLevel = DB::transactionLevel();
    $locks = [];
    DB::listen(function (QueryExecuted $query) use (&$locks): void {
        if (str_contains($query->sql, 'from "users"') && str_contains($query->sql, ' in (') && str_contains($query->sql, 'for update')) {
            $locks[] = ['level' => DB::transactionLevel(), 'owners' => $query->bindings];
        }
    });
    $headers = ['X-Telegram-Bot-Api-Secret-Token' => 'market-secret'];
    if ($deliveryFails) {
        Telegraph::fake(['answerCallbackQuery' => ['ok' => true, 'result' => true],
            'sendMessage' => ['ok' => false, 'error_code' => 429, 'parameters' => ['retry_after' => 5]]]);
        $this->postJson(route('telegraph.webhook', $bot->token), $update, $headers)->assertStatus(500);
    }
    Telegraph::fake(['answerCallbackQuery' => ['ok' => true, 'result' => true],
        'sendMessage' => ['ok' => true, 'result' => ['message_id' => 902]],
        'deleteMessage' => ['ok' => true, 'result' => true]]);

    $this->postJson(route('telegraph.webhook', $bot->token), $update, $headers)->assertNoContent();

    expect($locks)->not->toBeEmpty();
    foreach ($locks as $lock) {
        expect($lock)->toBe(['level' => $baseLevel + 1, 'owners' => [$seller->id, $buyer->id]]);
    }
    expect($pet->fresh()->user_id)->toBe($buyer->id);
    expect($buyer->fresh()->coins)->toBe(363);
    expect($seller->fresh()->coins)->toBe(157);
    $this->assertDatabaseCount('pet_sale_purchases', 1);
    $this->assertDatabaseCount('currency_transactions', 2);
    expect($seller->notifications()->get()->where('data.kind', 'pet_sold')->count())->toBe(1);
    $this->assertDatabaseHas('telegram_accounts', ['id' => $account->id, 'menu_message_id' => 902, 'menu_revision' => 2]);
})->with(['normal delivery' => false, 'delivery retried' => true]);

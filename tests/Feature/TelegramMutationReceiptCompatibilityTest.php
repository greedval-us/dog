<?php

use App\Http\Telegram\AdoptTelegramPet;
use App\Http\Telegram\ApplyTelegramPetSale;
use App\Models\TelegramAccount;
use App\Modules\Kennel\DTO\AdoptStarterPetData;
use App\Modules\Pets\Exceptions\PetUnavailable;
use Illuminate\Support\Facades\Http;

test('legacy telegram mutation receipts retain results and reject incompatible replays', function (string $kind, string $scenario) {
    Http::preventStrayRequests();
    $token = '00000000-0000-4000-8000-000000000001';
    $receipt = $kind === 'adoption'
        ? ['token' => $token, 'hash' => 'ccba3d9805d6997a6afbca9613850f13e825fa81faa05a717fa77363a90393bd', 'petId' => $scenario === 'invalid' ? '900' : 900]
        : ['token' => $token, 'hash' => '329c55af7a5e84e216b6461954b9ca93c4d8e638efcbe712191e486c769e6a5f', 'name' => $scenario === 'invalid' ? 123 : 'Rex'];
    $state = ['commands' => [['action' => 'home']], 'data' => [], $kind === 'adoption' ? 'kennelAdoption' : 'marketMutation' => $receipt];
    $account = TelegramAccount::factory()->create(['menu_state' => $state]);
    $replay = $kind === 'adoption'
        ? fn (): int => app(AdoptTelegramPet::class)->handle($account->user, new AdoptStarterPetData(123, 'Rex', $scenario === 'changed' ? 3 : 18), $token)
        : fn (): string => app(ApplyTelegramPetSale::class)->handle($account->user, $scenario === 'changed' ? 322 : 321, null, $token);

    if ($scenario === 'valid') {
        expect($replay())->toBe($kind === 'adoption' ? 900 : 'Rex');
    } else {
        expect($replay)->toThrow(PetUnavailable::class, 'telegram.errors.stale');
    }

    expect($account->fresh()->menu_state)->toBe($state);
    $this->assertDatabaseCount('pets', 0);
    $this->assertDatabaseCount('pet_sale_listings', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
    Http::assertNothingSent();
})->with([
    'original adoption result' => ['adoption', 'valid'],
    'changed adoption parameters' => ['adoption', 'changed'],
    'invalid adoption result type' => ['adoption', 'invalid'],
    'original sale result' => ['sale', 'valid'],
    'changed sale parameters' => ['sale', 'changed'],
    'invalid sale result type' => ['sale', 'invalid'],
]);

<?php

use App\Models\AssetUnlock;
use App\Models\Dog;
use App\Models\GameAsset;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Appearance\Actions\PurchasePetAsset;
use App\Modules\Appearance\DTO\PurchaseAssetData;
use App\Modules\Appearance\Enums\AssetCurrency;
use App\Modules\Appearance\Enums\AssetKind;
use App\Modules\Appearance\Queries\GetPetAppearance;
use App\Modules\Players\Enums\PlayerStatus;
use Database\Seeders\DogSeeder;
use Database\Seeders\GameAssetSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('local');
    Storage::disk('local')->put('appearance/test/portrait.png', 'private-portrait');
    Storage::disk('local')->put('appearance/test/icon.png', 'private-icon');
});

test('compatible free portrait and background can be selected without charging the account', function (bool $background) {
    $pet = Pet::factory()->create();
    $asset = $background
        ? GameAsset::factory()->background()->create()
        : GameAsset::factory()->create(['dog_id' => $pet->dog_id, 'coat_color' => $pet->coat_color]);

    $this->actingAs($pet->user)->from(route('dashboard'))
        ->put(route('pets.appearance.update', $pet), ['asset_id' => $asset->id])
        ->assertRedirect(route('dashboard'))->assertSessionHasNoErrors();

    expect($pet->fresh()->getAttribute($asset->kind->petColumn()))->toBe($asset->id);
    expect($pet->user->fresh()->coins)->toBe(0);
    $this->assertDatabaseCount('asset_unlocks', 0);
})->with([false, true]);

test('portraits and backgrounds can be bought with either currency without charging the other balance', function (AssetCurrency $currency, int $price, AssetKind $kind) {
    $user = User::factory()->create(['coins' => 130, 'gems' => 15]);
    $pet = Pet::factory()->for($user)->create();
    $asset = GameAsset::factory()->purchasableWithEitherCurrency()->create([
        'kind' => $kind,
        'dog_id' => $kind === AssetKind::Portrait ? $pet->dog_id : null,
        'coat_color' => $kind === AssetKind::Portrait ? $pet->coat_color : null,
    ]);

    $this->actingAs($user)->from(route('dashboard'))->post(route('pets.appearance.purchase', $pet), [
        'asset_id' => $asset->id, 'expected_price' => $price, 'expected_currency' => $currency->value,
        'user_id' => 999, 'price' => 0,
    ])->assertRedirect(route('dashboard'))->assertSessionHasNoErrors();

    expect($user->fresh()->coins)->toBe($currency === AssetCurrency::Coins ? 30 : 130);
    expect($user->fresh()->gems)->toBe($currency === AssetCurrency::Gems ? 5 : 15);
    expect($pet->fresh()->getAttribute($kind->petColumn()))->toBe($asset->id);
    $this->assertDatabaseHas('asset_unlocks', [
        'user_id' => $user->id, 'game_asset_id' => $asset->id, 'currency' => $currency->value, 'price_paid' => $price,
    ]);
})->with([[AssetCurrency::Coins, 100], [AssetCurrency::Gems, 10]])->with([AssetKind::Portrait, AssetKind::Background]);

test('repeated purchases and another compatible dog reuse one account unlock', function () {
    $user = User::factory()->create(['coins' => 100, 'gems' => 10]);
    $first = Pet::factory()->for($user)->create();
    $second = Pet::factory()->for($user)->create(['dog_id' => $first->dog_id, 'coat_color' => $first->coat_color]);
    $asset = GameAsset::factory()->purchasableWithEitherCurrency()->create(['dog_id' => $first->dog_id, 'coat_color' => $first->coat_color]);
    $purchase = app(PurchasePetAsset::class);
    $data = new PurchaseAssetData($asset->id, 100, AssetCurrency::Coins);
    $purchase->handle($user, $first->id, $data);
    $purchase->handle($user, $first->id, $data);
    $purchase->handle($user, $second->id, new PurchaseAssetData($asset->id, 10, AssetCurrency::Gems));

    $this->actingAs($user)->put(route('pets.appearance.update', $second), ['asset_id' => $asset->id])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->coins)->toBe(0);
    expect($user->fresh()->gems)->toBe(10);
    expect($second->fresh()->portrait_asset_id)->toBe($asset->id);
    expect($first->fresh()->portrait_asset_id)->toBe($asset->id);
    $this->assertDatabaseCount('asset_unlocks', 1);
    $this->assertDatabaseCount('currency_transactions', 1);
    $this->assertDatabaseHas('currency_transactions', [
        'user_id' => $user->id, 'currency' => 'coins', 'amount' => -100,
        'operation_key' => 'appearance:'.$asset->id, 'reason' => 'appearance_purchase',
    ]);
});

test('insufficient chosen balance or a stale price quote cannot spend the other currency', function (int $coins, int $gems, int $quote, string $currency) {
    $user = User::factory()->create(['coins' => $coins, 'gems' => $gems]);
    $pet = Pet::factory()->for($user)->create();
    $asset = GameAsset::factory()->background()->purchasableWithEitherCurrency()->create();

    $this->actingAs($user)->post(route('pets.appearance.purchase', $pet), [
        'asset_id' => $asset->id, 'expected_price' => $quote, 'expected_currency' => $currency,
    ])->assertSessionHasErrors('asset_id');

    expect($user->fresh()->coins)->toBe($coins);
    expect($user->fresh()->gems)->toBe($gems);
    expect($pet->fresh()->background_asset_id)->toBeNull();
    $this->assertDatabaseCount('asset_unlocks', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with([
    'insufficient coins' => [99, 15, 100, 'coins'],
    'insufficient gems' => [200, 9, 10, 'gems'],
    'changed coin price' => [200, 15, 1, 'coins'],
    'changed gem price' => [200, 15, 1, 'gems'],
    'other currency price' => [200, 15, 100, 'gems'],
]);

test('a currency without a configured price cannot buy an asset', function () {
    $user = User::factory()->create(['coins' => 100, 'gems' => 10]);
    $pet = Pet::factory()->for($user)->create();
    $asset = GameAsset::factory()->background()->paid()->create();

    $this->actingAs($user)->post(route('pets.appearance.purchase', $pet), [
        'asset_id' => $asset->id, 'expected_price' => 10, 'expected_currency' => 'gems',
    ])->assertSessionHasErrors('asset_id');

    expect($user->fresh()->coins)->toBe(100);
    expect($user->fresh()->gems)->toBe(10);
    expect($pet->fresh()->background_asset_id)->toBeNull();
    $this->assertDatabaseCount('asset_unlocks', 0);
});

test('paid artwork cannot be selected before unlocking it', function () {
    $pet = Pet::factory()->create();
    $asset = GameAsset::factory()->background()->paid()->create();

    $this->actingAs($pet->user)->put(route('pets.appearance.update', $pet), ['asset_id' => $asset->id])
        ->assertSessionHasErrors('asset_id');

    expect($pet->fresh()->background_asset_id)->toBeNull();
    $this->assertDatabaseCount('asset_unlocks', 0);
});

test('another players dog cannot be changed or used for purchasing', function (bool $purchase) {
    $pet = Pet::factory()->create();
    $intruder = User::factory()->create(['coins' => 100]);
    $asset = GameAsset::factory()->background()->paid()->create();
    $route = route($purchase ? 'pets.appearance.purchase' : 'pets.appearance.update', $pet);
    $data = ['asset_id' => $asset->id, 'expected_price' => 100, 'expected_currency' => 'coins'];
    $this->actingAs($intruder);
    ($purchase ? $this->post($route, $data) : $this->put($route, $data))->assertNotFound();

    expect($intruder->fresh()->coins)->toBe(100);
    expect($pet->fresh()->background_asset_id)->toBeNull();
    $this->assertDatabaseCount('asset_unlocks', 0);
})->with([false, true]);

test('incompatible inactive missing or malformed assets cannot be bought', function (string $reason) {
    $pet = Pet::factory()->create();
    $pet->user->forceFill(['coins' => 100])->save();
    $asset = GameAsset::factory()->paid()->create(['dog_id' => $pet->dog_id, 'coat_color' => $pet->coat_color]);
    match ($reason) {
        'breed' => $asset->update(['dog_id' => Dog::factory()->create()->id]),
        'coat' => $asset->update(['coat_color' => 'another_coat']),
        'inactive' => $asset->update(['is_active' => false]),
        'missing' => Storage::disk('local')->delete($asset->image_path),
        'malformed' => $asset->update(['coins_price' => 0]),
    };

    $this->actingAs($pet->user)->post(route('pets.appearance.purchase', $pet), [
        'asset_id' => $asset->id, 'expected_price' => 100, 'expected_currency' => 'coins',
    ])->assertSessionHasErrors('asset_id');

    expect($pet->user->fresh()->coins)->toBe(100);
    expect($pet->fresh()->portrait_asset_id)->toBeNull();
    $this->assertDatabaseCount('asset_unlocks', 0);
})->with(['breed', 'coat', 'inactive', 'missing', 'malformed']);

test('a failed unlock insert rolls the balance and selection back', function () {
    $pet = Pet::factory()->create();
    $pet->user->forceFill(['coins' => 100])->save();
    $asset = GameAsset::factory()->background()->paid()->create();
    $events = AssetUnlock::getEventDispatcher();
    AssetUnlock::setEventDispatcher(clone $events);

    try {
        AssetUnlock::creating(fn () => throw new RuntimeException('Storage failure'));
        expect(fn () => app(PurchasePetAsset::class)->handle($pet->user, $pet->id, new PurchaseAssetData($asset->id, 100, AssetCurrency::Coins)))
            ->toThrow(RuntimeException::class, 'Storage failure');
    } finally {
        AssetUnlock::setEventDispatcher($events);
    }

    expect($pet->user->fresh()->coins)->toBe(100);
    expect($pet->fresh()->background_asset_id)->toBeNull();
    $this->assertDatabaseCount('asset_unlocks', 0);
});

test('blocked accounts cannot buy appearances', function () {
    $pet = Pet::factory()->create();
    $pet->user->forceFill(['status' => PlayerStatus::Blocked, 'coins' => 100])->save();
    $asset = GameAsset::factory()->background()->paid()->create();

    $this->actingAs($pet->user)->post(route('pets.appearance.purchase', $pet), [
        'asset_id' => $asset->id, 'expected_price' => 100, 'expected_currency' => 'coins',
    ])->assertForbidden();

    expect($pet->user->fresh()->coins)->toBe(100);
    $this->assertDatabaseCount('asset_unlocks', 0);
});

test('the dashboard exposes only matching usable catalogue entries and never storage paths', function () {
    $pet = Pet::factory()->create();
    $free = GameAsset::factory()->create(['dog_id' => $pet->dog_id, 'coat_color' => $pet->coat_color]);
    $paid = GameAsset::factory()->purchasableWithEitherCurrency()->create(['dog_id' => $pet->dog_id, 'coat_color' => $pet->coat_color]);
    GameAsset::factory()->create();
    GameAsset::factory()->background()->create(['is_active' => false]);
    GameAsset::factory()->background()->create(['image_path' => 'appearance/missing.png']);

    $this->actingAs($pet->user)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('appearance.portraitId', $free->id)
        ->where('appearance.backgroundId', null)
        ->has('appearance.assets', 2)
        ->where('appearance.assets.0.id', $free->id)
        ->where('appearance.assets.0.unlocked', true)
        ->where('appearance.assets.0.prices', [])
        ->where('appearance.assets.1.id', $paid->id)
        ->where('appearance.assets.1.unlocked', false)
        ->where('appearance.assets.1.prices', [['currency' => 'coins', 'amount' => 100], ['currency' => 'gems', 'amount' => 10]])
        ->missing('appearance.assets.0.image_path')
        ->missing('appearance.assets.0.icon_path')
    );
});

test('a dog transferred to another owner falls back to a free portrait without sharing purchases', function () {
    $pet = Pet::factory()->create();
    $free = GameAsset::factory()->create(['dog_id' => $pet->dog_id, 'coat_color' => $pet->coat_color]);
    $paid = GameAsset::factory()->paid()->create(['dog_id' => $pet->dog_id, 'coat_color' => $pet->coat_color]);
    AssetUnlock::factory()->for($pet->user)->create(['game_asset_id' => $paid->id]);
    $newOwner = User::factory()->create();
    $pet->forceFill(['portrait_asset_id' => $paid->id, 'user_id' => $newOwner->id])->save();

    $appearance = app(GetPetAppearance::class)->handle($newOwner, $pet->id, 'en');
    expect($appearance->portraitId)->toBe($free->id);
    expect($appearance->assets[1]->unlocked)->toBeFalse();
});

test('private asset previews require login and use no store headers', function (string $variant, string $expected) {
    $asset = GameAsset::factory()->paid()->create();
    $url = route('assets.image', ['asset' => $asset, 'variant' => $variant]);
    $this->get($url)->assertRedirect(route('login'));

    $response = $this->actingAs(User::factory()->create())->get($url)->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->streamedContent())->toBe($expected);
})->with([['image', 'private-portrait'], ['icon', 'private-icon']]);

test('catalogue media cannot disclose unrelated private files', function (string $path) {
    Storage::disk('local')->put('avatars/private.png', 'secret');
    $asset = GameAsset::factory()->create(['image_path' => $path]);

    $this->actingAs(User::factory()->create())->get(route('assets.image', ['asset' => $asset, 'variant' => 'image']))
        ->assertNotFound();
})->with(['avatars/private.png', 'appearance/../avatars/private.png', 'C:/secret.png']);

test('the seeded catalogue covers every starter coat with free and paid poses and preserves edited prices', function () {
    Storage::forgetDisk('local');
    $this->seed(DogSeeder::class);
    $this->seed(GameAssetSeeder::class);

    foreach (Dog::query()->get() as $dog) {
        foreach (array_keys($dog->coat_colors) as $coat) {
            $assets = GameAsset::query()->where('dog_id', $dog->id)->where('coat_color', $coat)->get();
            expect($assets)->toHaveCount(2);
            expect($assets->filter(fn (GameAsset $asset): bool => $asset->isFree()))->toHaveCount(1);
            expect($assets->where('coins_price', 100)->where('gems_price', 10))->toHaveCount(1);
            foreach ($assets as $asset) {
                expect($asset->hasFiles())->toBeTrue($asset->code);
            }
        }
    }

    $this->assertDatabaseCount('game_assets', 21);
    $backgrounds = GameAsset::query()->where('kind', AssetKind::Background)->get();
    expect($backgrounds)->toHaveCount(3);
    foreach ($backgrounds as $asset) {
        expect($asset->hasFiles())->toBeTrue($asset->code);
    }
    $paid = GameAsset::query()->where('code', 'moonlit_lake')->firstOrFail();
    expect($paid->coins_price)->toBe(100);
    expect($paid->gems_price)->toBe(10);
    $paid->update(['coins_price' => 250, 'gems_price' => 25]);
    $this->seed(GameAssetSeeder::class);
    expect($paid->fresh()->coins_price)->toBe(250);
    expect($paid->fresh()->gems_price)->toBe(25);
    $this->assertDatabaseCount('game_assets', 21);
});

test('currency price migration preserves existing prices purchases and selected artwork', function () {
    $migration = require database_path('migrations/2026_09_25_192119_add_currency_prices_to_game_assets.php');
    $migration->down();
    $pet = Pet::factory()->create();
    $attributes = [
        'kind' => 'background', 'name' => json_encode(['ru' => 'Фон', 'en' => 'Background']),
        'image_path' => 'appearance/test/portrait.png', 'icon_path' => 'appearance/test/icon.png',
    ];
    $free = DB::table('game_assets')->insertGetId([...$attributes, 'code' => 'free', 'price' => 0]);
    $coins = DB::table('game_assets')->insertGetId([...$attributes, 'code' => 'coins', 'price' => 250, 'currency' => 'coins']);
    $gems = DB::table('game_assets')->insertGetId([...$attributes, 'code' => 'gems', 'price' => 25, 'currency' => 'gems']);
    AssetUnlock::factory()->for($pet->user)->create(['game_asset_id' => $coins, 'currency' => AssetCurrency::Coins, 'price_paid' => 200]);
    $pet->forceFill(['background_asset_id' => $coins])->save();

    $migration->up();

    $this->assertDatabaseHas('game_assets', ['id' => $free, 'coins_price' => null, 'gems_price' => null]);
    $this->assertDatabaseHas('game_assets', ['id' => $coins, 'coins_price' => 250, 'gems_price' => 10]);
    $this->assertDatabaseHas('game_assets', ['id' => $gems, 'coins_price' => 100, 'gems_price' => 25]);
    $this->assertDatabaseHas('asset_unlocks', ['user_id' => $pet->user_id, 'game_asset_id' => $coins, 'currency' => 'coins', 'price_paid' => 200]);
    expect($pet->fresh()->background_asset_id)->toBe($coins);
    expect(Schema::hasColumn('game_assets', 'price'))->toBeFalse();
    expect(Schema::hasColumn('game_assets', 'currency'))->toBeFalse();
    expect(fn () => $migration->down())->toThrow(RuntimeException::class, 'Cannot roll back two asset prices into one');
});

<?php

use App\Models\User;
use App\Modules\Appearance\Actions\PurchasePetAsset;
use App\Modules\Appearance\DTO\PurchaseAssetData;
use App\Modules\Appearance\Enums\AssetCurrency;
use App\Modules\Pets\Actions\PurchasePetSlot;
use App\Modules\Pets\DTO\PurchasePetSlotData;
use App\Modules\Players\Exceptions\InsufficientFunds;
use App\Modules\Players\Services\PlayerWallet;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('testing')) {
    throw new RuntimeException('Database workers may only run in the testing environment.');
}

$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
config([
    'database.default' => 'pgsql',
    'database.connections.pgsql' => $input['connection'],
    'filesystems.disks.local.root' => $input['storage'],
]);
DB::purge('pgsql');
DB::select("SELECT set_config('application_name', ?, false)", [$input['name']]);
DB::statement("SET lock_timeout = '12s'");
$user = User::query()->findOrFail($input['user_id']);

try {
    match ($input['action']) {
        'slot' => app(PurchasePetSlot::class)->handle($user, new PurchasePetSlotData(2, 'coins', 100)),
        'wallet' => app(PlayerWallet::class)->change($user, 'coins', -80, $input['name'], 'purchase'),
        'appearance' => app(PurchasePetAsset::class)->handle($user, $input['pet_id'], new PurchaseAssetData($input['asset_id'], 100, AssetCurrency::Coins)),
    };
    echo 'ok';
} catch (InsufficientFunds) {
    echo 'insufficient';
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage());
    exit(1);
}

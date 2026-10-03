<?php

use App\Models\User;
use App\Modules\Appearance\Actions\PurchasePetAsset;
use App\Modules\Appearance\DTO\PurchaseAssetData;
use App\Modules\Appearance\Enums\AssetCurrency;
use App\Modules\Kennel\Actions\PurchaseKennelPet;
use App\Modules\Kennel\DTO\PurchaseKennelPetData;
use App\Modules\Pets\Actions\CompleteDogWork;
use App\Modules\Pets\Actions\CompletePetCare;
use App\Modules\Pets\Actions\GenerateDogWorkBoard;
use App\Modules\Pets\Actions\PurchasePetSlot;
use App\Modules\Pets\Actions\PurchaseVeterinaryService;
use App\Modules\Pets\Actions\RetirePet;
use App\Modules\Pets\Actions\StartDogWork;
use App\Modules\Pets\Actions\StartPetCare;
use App\Modules\Pets\DTO\PurchasePetSlotData;
use App\Modules\Pets\DTO\PurchaseVeterinaryServiceData;
use App\Modules\Pets\DTO\StartDogWorkData;
use App\Modules\Pets\Enums\VeterinaryService;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Players\Exceptions\InsufficientFunds;
use App\Modules\Players\Services\PlayerWallet;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Date;
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
if (isset($input['at'])) {
    Date::setTestNow($input['at']);
}

try {
    $result = match ($input['action']) {
        'slot' => app(PurchasePetSlot::class)->handle($user, new PurchasePetSlotData(2, 'coins', 100)),
        'wallet' => app(PlayerWallet::class)->change($user, 'coins', -80, $input['name'], 'purchase'),
        'appearance' => app(PurchasePetAsset::class)->handle($user, $input['pet_id'], new PurchaseAssetData($input['asset_id'], 100, AssetCurrency::Coins)),
        'care-start' => app(StartPetCare::class)->handle($user, $input['pet_id'], $input['variant'], $input['items'], $input['token']),
        'care-complete' => app(CompletePetCare::class)->handle($user, $input['pet_id'], $input['token']),
        'retire' => app(RetirePet::class)->handle($user, $input['pet_id']),
        'dog-work-board' => app(GenerateDogWorkBoard::class)->handle(),
        'dog-work-start' => app(StartDogWork::class)->handle($user, $input['pet_id'], new StartDogWorkData($input['offer_id'], $input['token'])),
        'dog-work-complete' => app(CompleteDogWork::class)->handle($user, $input['token']),
        'kennel' => app(PurchaseKennelPet::class)->handle($user, new PurchaseKennelPetData($input['dog_id'], 'Luna', 500, $input['token'])),
        'veterinarian' => app(PurchaseVeterinaryService::class)->handle($user, new PurchaseVeterinaryServiceData(
            $input['pet_id'], VeterinaryService::from($input['service']), $input['episode_id'] ?? null, $input['price'], $input['token'],
        )),
    };
    echo $input['action'] === 'care-complete' ? ($result ? 'applied' : 'replayed') : 'ok';
} catch (InsufficientFunds) {
    echo 'insufficient';
} catch (PetUnavailable) {
    echo 'unavailable';
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage());
    exit(1);
}

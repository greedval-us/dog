<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Dog;
use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\Pet;
use App\Models\PetCareAction;
use App\Models\Training;
use App\Models\User;
use App\Modules\Pets\Enums\PetStat;
use Database\Seeders\DogSeeder;
use Database\Seeders\GameAssetSeeder;
use Database\Seeders\ShopItemSeeder;
use Database\Seeders\TrainingSeeder;
use Database\Seeders\WorkTypeSeeder;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$app = require __DIR__.'/load-bootstrap.php';
$mode = $argv[1] ?? '';

if ($mode === 'inspect') {
    $actions = PetCareAction::query()->where('created_at', '>=', file_get_contents(storage_path('fixture-created-at')));
    echo json_encode([
        'players' => User::query()->count(),
        'pets' => Pet::query()->count(),
        'sessions' => count(array_filter(json_decode(file_get_contents(storage_path('participants.json')), true, flags: JSON_THROW_ON_ERROR)['players'],
            fn (array $player): bool => app('session')->driver()->getHandler()->read($player['sessionId']) !== ''
        )),
        'started' => (clone $actions)->count(),
        'completed' => (clone $actions)->whereNotNull('completed_at')->count(),
        'trainingsStarted' => (clone $actions)->where('group', 'training')->count(),
        'trainingsCompleted' => (clone $actions)->where('group', 'training')->whereNotNull('completed_at')->count(),
        'activePets' => Pet::query()->whereNotNull('activity')->count(),
        'trainedPets' => Pet::query()->where('speed', '>', 20)->count(),
        'itemsUsed' => DB::table('item_usages')->count(),
        'invalidPetEnergy' => Pet::query()->where('energy', '<', 0)->orWhereColumn('energy', '>', 'energy_max')->count(),
        'invalidInventoryUses' => InventoryItem::query()->where('remaining_uses', '<', 0)->count(),
        'databaseVersion' => DB::selectOne('SELECT version()')->version,
    ], JSON_THROW_ON_ERROR);
    exit;
}

$count = filter_var($argv[2] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
if ($mode !== 'prepare' || $count === false
    || DB::table('pg_namespace')->where('nspname', config('database.connections.pgsql.search_path'))->exists()) {
    throw new RuntimeException('Prepare requires a new fixture schema and 1–1000 players.');
}

DB::statement('CREATE SCHEMA "'.config('database.connections.pgsql.search_path').'"');
Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
foreach ([DogSeeder::class, GameAssetSeeder::class, ShopItemSeeder::class, WorkTypeSeeder::class, TrainingSeeder::class] as $seeder) {
    Artisan::call('db:seed', ['--class' => $seeder, '--force' => true, '--no-interaction' => true]);
}

$dog = Dog::query()->where('breed', 'pit_bull')->firstOrFail();
$items = Item::query()->with('effectRules.statusEffect')->whereIn('code', ['daily_kibble', 'rubber_ball', 'training_cones'])->get();
$training = Training::query()->where('code', 'sprint')->firstOrFail();
$cookieName = config('session.cookie');
$encrypter = app('encrypter');
$guardKey = Auth::guard('web')->getName();
$participants = [];

DB::transaction(function () use ($count, $dog, $items, $cookieName, $encrypter, $guardKey, &$participants): void {
    for ($index = 0; $index < $count; $index++) {
        $user = User::factory()->create(['name' => 'Load player '.$index, 'username' => 'load_player_'.$index, 'email' => 'load'.$index.'@example.test']);
        $states = ['mood' => 80, 'bond' => 70, 'energy' => 100];
        foreach (PetStat::cases() as $stat) {
            $states[$stat->value] = 20;
        }
        $pet = Pet::factory()->for($user)->for($dog)->create($states);
        $pet->update(['hydration' => $pet->hydration_max * 0.7]);
        $sportsId = null;
        foreach ($items as $item) {
            $instance = InventoryItem::factory()->for($user)->for($item)->create([
                ...$item->inventorySnapshot(), 'remaining_uses' => $item->usage_limit,
            ]);
            if ($item->code === 'training_cones') {
                $sportsId = $instance->id;
            }
        }
        PetCareAction::factory()->count(20)->create([
            'user_id' => $user->id, 'pet_id' => $pet->id,
            'ends_at' => now()->subDay(), 'available_at' => now()->subDay(),
            'completed_at' => now()->subDay(), 'created_at' => now()->subDay(),
        ]);
        $sessionId = Str::random(40);
        $csrf = Str::random(40);
        $session = ['_token' => $csrf, $guardKey => $user->id];
        app('session')->driver()->getHandler()->write($sessionId,
            config('session.serialization') === 'json' ? json_encode($session, JSON_THROW_ON_ERROR) : serialize($session)
        );
        $cookie = $encrypter->encrypt(CookieValuePrefix::create($cookieName, $encrypter->getKey()).$sessionId, false);
        $participants[] = ['id' => $user->id, 'pet' => $pet->id, 'sports' => $sportsId, 'csrf' => $csrf, 'sessionId' => $sessionId, 'cookie' => $cookieName.'='.rawurlencode($cookie)];
    }
});

file_put_contents(storage_path('fixture-created-at'), now()->toDateTimeString());
file_put_contents(storage_path('participants.json'), json_encode([
    'version' => app(HandleInertiaRequests::class)->version(Request::create(config('app.url'))),
    'training' => $training->id, 'trainingDuration' => $training->duration_seconds,
    'waterDuration' => config('pet_care.options.water.duration'), 'players' => $participants,
], JSON_THROW_ON_ERROR));
echo json_encode(['players' => $count, 'pets' => Pet::query()->count(), 'inventory' => InventoryItem::query()->count(), 'history' => PetCareAction::query()->count(), 'php' => PHP_VERSION, 'laravel' => app()->version()], JSON_THROW_ON_ERROR);

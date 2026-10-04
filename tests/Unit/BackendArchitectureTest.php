<?php

use App\Modules\Inventory\Services\InventoryConsumption;
use App\Modules\Pets\Services\PetLifecycle;
use App\Modules\Players\Services\PlayerProgress;
use App\Modules\Players\Services\PlayerWallet;

$moduleDirectories = glob(dirname(__DIR__, 2).'/app/Modules/*', GLOB_ONLYDIR);
$modules = array_map(fn (string $directory): string => 'App\\Modules\\'.basename($directory), $moduleDirectories);
$layers = [];
$publicOperations = [
    PetLifecycle::class,
    PlayerWallet::class,
    PlayerProgress::class,
    InventoryConsumption::class,
];

foreach (['Actions', 'Queries', 'DTO', 'Enums', 'Calculators', 'Generators', 'Services'] as $layer) {
    $layers[$layer] = array_map(
        fn (string $directory): string => 'App\\Modules\\'.basename(dirname($directory)).'\\'.$layer,
        glob(dirname(__DIR__, 2).'/app/Modules/*/'.$layer, GLOB_ONLYDIR),
    );
}

arch('modules do not depend on HTTP requests or build HTTP responses')
    ->expect('App\Modules')
    ->not->toUse(['App\Http', 'Illuminate\Http', 'Inertia', 'request', 'response', 'redirect', 'abort', 'abort_if', 'abort_unless', 'session']);

arch('public lifecycle operation hides locked persistence and care receipt details')
    ->expect(PetLifecycle::class)
    ->not->toHavePublicMethodsBesides(['__construct', 'synchronizeOwner', 'assertCanAdvance']);

arch('module DTOs are immutable and independent of the application operations')
    ->expect($layers['DTO'])
    ->toBeFinal()
    ->toBeReadonly()
    ->not->toUse(['App\Actions', ...$layers['Actions'], ...$layers['Queries'], ...$layers['Services']]);

arch('queries do not invoke write actions or gameplay services')
    ->expect($layers['Queries'])
    ->not->toUse(['App\Actions', ...$layers['Actions'], ...$layers['Services']]);

arch('models do not orchestrate application operations or depend on HTTP')
    ->expect('App\Models')
    ->not->toUse([
        'App\Http', 'App\Actions', 'Inertia',
        ...$layers['Actions'], ...$layers['Queries'], ...$layers['Services'],
    ]);

arch('Fortify actions remain independent of HTTP requests and Inertia')
    ->expect('App\Actions')
    ->not->toUse(['App\Http', 'Illuminate\Http', 'Inertia', 'request', 'response', 'session']);

arch('domain enums are string backed and independent of the other application layers')
    ->expect($layers['Enums'])
    ->toBeStringBackedEnums()
    ->not->toUse(['App\Models', 'App\Actions', 'Illuminate', ...$layers['DTO'], ...$layers['Actions'], ...$layers['Queries'], ...$layers['Services']]);

arch('calculations and generators do not load application state or access the framework')
    ->expect([...$layers['Calculators'], ...$layers['Generators']])
    ->not->toUse([
        'App\Models', 'App\Providers', 'App\Actions', 'Illuminate', 'Carbon',
        'app', 'resolve', 'config', 'auth', 'cache', 'event', 'dispatch',
        'now', 'today', 'time', 'microtime', 'date', 'rand', 'mt_rand', 'random_int', 'random_bytes',
        ...$layers['Actions'], ...$layers['Queries'], ...$layers['Services'],
    ]);

arch('calculators are deterministic and do not draw random values')
    ->expect($layers['Calculators'])
    ->not->toUse(['Random', ...$layers['Generators']]);

foreach ($modules as $module) {
    $otherOperations = [];

    foreach (array_diff($modules, [$module]) as $otherModule) {
        foreach (['Actions', 'Queries', 'Generators'] as $layer) {
            $otherOperations[] = $otherModule.'\\'.$layer;
        }

        $serviceDirectory = dirname(__DIR__, 2).'/'.str_replace('\\', '/', str_replace('App\\', 'app\\', $otherModule)).'/Services';
        if (is_dir($serviceDirectory)) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($serviceDirectory, FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $relative = substr($file->getPathname(), strlen($serviceDirectory) + 1, -4);
                $service = $otherModule.'\\Services\\'.str_replace('/', '\\', $relative);
                if (! in_array($service, $publicOperations, true)) {
                    $otherOperations[] = $service;
                }
            }
        }
    }

    arch($module.' does not call another module application operations')
        ->expect($module)
        ->not->toUse($otherOperations);
}

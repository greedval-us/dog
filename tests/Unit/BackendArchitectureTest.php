<?php

$moduleDirectories = glob(dirname(__DIR__, 2).'/app/Modules/*', GLOB_ONLYDIR);
$modules = array_map(fn (string $directory): string => 'App\\Modules\\'.basename($directory), $moduleDirectories);
$layers = [];

foreach (['Actions', 'Queries', 'DTO', 'Enums', 'Calculators', 'Generators', 'Services'] as $layer) {
    $layers[$layer] = array_map(
        fn (string $directory): string => 'App\\Modules\\'.basename(dirname($directory)).'\\'.$layer,
        glob(dirname(__DIR__, 2).'/app/Modules/*/'.$layer, GLOB_ONLYDIR),
    );
}

arch('modules do not depend on HTTP requests or build HTTP responses')
    ->expect('App\Modules')
    ->not->toUse(['App\Http', 'Illuminate\Http', 'Inertia', 'request', 'response', 'redirect', 'abort', 'abort_if', 'abort_unless', 'session']);

arch('module DTOs are immutable and independent of the application operations')
    ->expect($layers['DTO'])
    ->toBeFinal()
    ->toBeReadonly()
    ->not->toUse(['App\Actions', ...$layers['Actions'], ...$layers['Queries'], ...$layers['Services']]);

arch('queries do not invoke write actions or gameplay services')
    ->expect($layers['Queries'])
    ->not->toUse(['App\Actions', ...$layers['Actions'], ...$layers['Services']]);

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
    }

    arch($module.' does not call another module application operations')
        ->expect($module)
        ->not->toUse($otherOperations);
}

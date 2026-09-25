<?php

arch('data objects are immutable and independent of the HTTP and application layers')
    ->expect('App\Data')
    ->toBeFinal()
    ->toBeReadonly()
    ->not->toUse(['App\Http', 'App\Actions', 'App\Queries', 'Illuminate\Http', 'Inertia', 'request', 'session']);

arch('queries do not invoke write actions or build HTTP responses')
    ->expect('App\Queries')
    ->not->toUse(['App\Actions', 'App\Http', 'Illuminate\Http', 'Inertia', 'request', 'response', 'session']);

arch('actions do not depend on HTTP requests or Inertia')
    ->expect('App\Actions')
    ->not->toUse(['App\Http', 'Illuminate\Http', 'Inertia', 'request', 'response', 'session']);

arch('domain enums are string backed and independent of the other application layers')
    ->expect('App\Enums')
    ->toBeStringBackedEnums()
    ->not->toUse(['App\Models', 'App\Data', 'App\Actions', 'App\Queries', 'App\Http']);

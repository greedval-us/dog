<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('events:process')->everyMinute()->withoutOverlapping();
Schedule::command('pets:maintain-history')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('pets:sync-lifecycle')->everyMinute()->withoutOverlapping();
Schedule::command('puppies:transfer-expired')->everyMinute()->withoutOverlapping();
Schedule::command('shop:restock-ammunition')->everyMinute()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

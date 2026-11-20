<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Allow previously queued game-menu refreshes to drain after the switch to alerts. */
final class RefreshTelegramCare implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $accountId, public int $petId, public string $token, public int $revision) {}

    public function handle(): void {}
}

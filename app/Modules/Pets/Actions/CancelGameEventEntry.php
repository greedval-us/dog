<?php

namespace App\Modules\Pets\Actions;

use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\User;
use App\Modules\Pets\Exceptions\GameEventUnavailable;
use App\Modules\Players\Services\PlayerWallet;
use Illuminate\Support\Facades\DB;

final class CancelGameEventEntry
{
    public function __construct(private PlayerWallet $wallet) {}

    public function handle(User $user, int $entryId): GameEventEntry
    {
        $candidate = GameEventEntry::query()->where('user_id', $user->id)->findOrFail($entryId);

        return DB::transaction(function () use ($user, $candidate): GameEventEntry {
            $event = GameEvent::query()->lockForUpdate()->findOrFail($candidate->game_event_id);
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);
            $entry = GameEventEntry::query()->lockForUpdate()->findOrFail($candidate->id);
            if ($entry->status === 'cancelled') {
                return $entry;
            }
            if ($entry->status !== 'registered' || $event->status !== 'registration' || now()->greaterThanOrEqualTo($event->closes_at)) {
                throw new GameEventUnavailable('events.errors.closed');
            }
            $this->wallet->change($owner, 'coins', $entry->fee, 'event-entry:'.$entry->id.':refund', 'event_refund');
            $entry->update(['status' => 'cancelled', 'refunded_at' => now()]);

            return $entry;
        }, attempts: 3);
    }
}

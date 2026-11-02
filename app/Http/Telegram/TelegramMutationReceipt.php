<?php

namespace App\Http\Telegram;

use App\Models\TelegramAccount;
use App\Models\User;
use App\Modules\Pets\Exceptions\PetUnavailable;
use Closure;
use Illuminate\Support\Facades\DB;

final class TelegramMutationReceipt
{
    /**
     * @param  list<int|string|null>  $parameters
     * @param  Closure(User): array<string, mixed>  $mutation
     * @return array<string, mixed>
     */
    public function handle(User $user, string $stateKey, string $token, array $parameters, Closure $mutation): array
    {
        return DB::transaction(function () use ($user, $stateKey, $token, $parameters, $mutation): array {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);
            $account = TelegramAccount::query()->where('user_id', $owner->id)->lockForUpdate()->firstOrFail();
            $state = $account->menu_state;
            $hash = hash('sha256', serialize($parameters));
            $receipt = $state[$stateKey] ?? null;
            if (is_array($receipt) && ($receipt['token'] ?? null) === $token) {
                if (($receipt['hash'] ?? null) !== $hash) {
                    throw new PetUnavailable('telegram.errors.stale');
                }

                return $receipt;
            }
            $receipt = ['token' => $token, 'hash' => $hash] + $mutation($owner);
            $state[$stateKey] = $receipt;
            $account->update(['menu_state' => $state]);

            return $receipt;
        }, attempts: 3);
    }
}

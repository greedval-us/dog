<?php

namespace App\Modules\Telegram\Actions;

use App\Models\TelegramAccount;
use App\Models\TelegramLinkToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DisconnectTelegramAccount
{
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            User::query()->lockForUpdate()->findOrFail($user->id);
            TelegramAccount::query()->where('user_id', $user->id)->delete();
            TelegramLinkToken::query()->where('user_id', $user->id)->delete();
        });
    }
}

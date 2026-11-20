<?php

namespace App\Modules\Telegram\Queries;

use App\Models\TelegramAccount;
use App\Models\TelegramLinkToken;
use App\Models\User;

final class GetTelegramConnection
{
    public function __construct(private GetTelegramBot $bots, private GetTelegramBotUrl $botUrls) {}

    /**
     * @return array{available: bool, status: string, account: array{firstName: string, username: ?string, connectedAt: string}|null, link: array{url: string, expiresAt: string}|null, botUrl: ?string, serverNow: string}
     */
    public function handle(User $user, ?string $token = null): array
    {
        $account = TelegramAccount::query()->with('chat.bot')->where('user_id', $user->id)->first();
        $link = $account === null && $token !== null
            ? TelegramLinkToken::query()->where('user_id', $user->id)->where('token_hash', hash('sha256', $token))->first()
            : null;
        $bot = $account?->chat->bot ?? $this->bots->handle($link?->telegraph_bot_id);
        $botUrl = $bot !== null && (string) config('telegraph.webhook.secret') !== '' ? $this->botUrls->handle($bot) : null;
        $available = $botUrl !== null;
        $data = [
            'available' => $available,
            'status' => $available ? 'disconnected' : 'unavailable',
            'account' => null,
            'link' => null,
            'botUrl' => $botUrl,
            'serverNow' => now()->toIso8601String(),
        ];

        if ($account !== null) {
            $data['status'] = 'connected';
            $data['account'] = ['firstName' => $account->first_name, 'username' => $account->username, 'connectedAt' => $account->linked_at->toIso8601String()];

            return $data;
        }

        if (! $available || $token === null) {
            return $data;
        }

        if ($link === null) {
            return $data;
        }

        if ($link->expires_at->lessThanOrEqualTo(now())) {
            $data['status'] = 'expired';

            return $data;
        }

        $data['status'] = 'pending';
        $data['link'] = ['url' => $data['botUrl'].'?start='.$token, 'expiresAt' => $link->expires_at->toIso8601String()];

        return $data;
    }
}

<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Telegram\MenuMessenger;
use App\Http\Telegram\TelegramDeliveryFailed;
use App\Models\TelegramAccount;
use App\Models\User;
use App\Modules\Telegram\Actions\CreateTelegramLink;
use App\Modules\Telegram\Actions\DisconnectTelegramAccount;
use App\Modules\Telegram\Exceptions\TelegramUnavailable;
use App\Modules\Telegram\Services\MenuSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class TelegramConnectionController extends Controller
{
    public function store(Request $request, CreateTelegramLink $createLink): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        try {
            $link = $createLink->handle($user);
        } catch (TelegramUnavailable $exception) {
            return to_route('profile.edit')->withErrors(['telegram' => __($exception->getMessage())]);
        }

        $request->session()->put('telegram.link_token', $link->token);

        return to_route('profile.edit');
    }

    public function destroy(Request $request, DisconnectTelegramAccount $disconnect, MenuMessenger $messenger, MenuSession $menus): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $account = TelegramAccount::query()->with('chat')->where('user_id', $user->id)->first();
        if ($account === null) {
            $disconnect->handle($user);
        } else {
            $menus->withLock($account->chat->telegraph_bot_id, $account->telegram_user_id, function () use ($user, $account, $disconnect, $messenger): void {
                $account = $account->fresh();
                $disconnect->handle($user);
                if ($account?->menu_message_id !== null) {
                    try {
                        $messenger->welcome($account->chat, messageId: $account->menu_message_id);
                    } catch (TelegramDeliveryFailed) {
                        Log::notice('Telegram could not refresh the disconnected menu.', ['account_id' => $account->id]);
                    }
                }
            });
        }
        $request->session()->forget('telegram.link_token');
        Inertia::flash('toast', ['type' => 'success', 'message' => __('telegram.disconnected')]);

        return to_route('profile.edit');
    }
}

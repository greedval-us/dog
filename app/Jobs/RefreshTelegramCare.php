<?php

namespace App\Jobs;

use App\Http\Telegram\GameMenu;
use App\Http\Telegram\MenuMessenger;
use App\Http\Telegram\TelegramDeliveryFailed;
use App\Models\TelegramAccount;
use App\Modules\Pets\Actions\CompletePetCare;
use App\Modules\Pets\Exceptions\PendingGameEventRegistration;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Players\Enums\PlayerStatus;
use App\Modules\Telegram\Services\MenuSession;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Queue\Queueable;

final class RefreshTelegramCare implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 120];
    }

    public function __construct(public int $accountId, public int $petId, public string $token, public int $revision) {}

    public function handle(CompletePetCare $complete, GameMenu $menu, MenuMessenger $messenger, MenuSession $menus): void
    {
        $account = TelegramAccount::query()->with(['user', 'chat'])->find($this->accountId);
        if ($account === null || $account->user->status !== PlayerStatus::Active) {
            return;
        }
        $menus->withLock($account->chat->telegraph_bot_id, $account->telegram_user_id, function () use ($account, $complete, $menu, $messenger): void {
            if ($account->fresh() === null) {
                return;
            }
            $user = $account->user;
            try {
                $complete->handle($user, $this->petId, $this->token);
            } catch (PendingGameEventRegistration) {
                $this->release(60);

                return;
            } catch (PetUnavailable|ModelNotFoundException) {
                return;
            }
            $account->refresh();
            if ($account->menu_revision !== $this->revision || ($account->menu_state['data']['care']['token'] ?? null) !== $this->token) {
                return;
            }
            app()->setLocale($user->preferredLocale());
            $user->refresh();
            try {
                $messenger->publish($account, $menu->render($user, ['action' => 'pet', 'petId' => $this->petId]));
            } catch (TelegramDeliveryFailed $exception) {
                if ($exception->retryAfter < 1) {
                    throw $exception;
                }
                $this->release($exception->retryAfter);
            }
        });
    }
}

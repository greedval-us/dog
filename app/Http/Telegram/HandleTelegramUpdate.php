<?php

namespace App\Http\Telegram;

use App\Models\TelegramAccount;
use App\Modules\Inventory\Exceptions\ItemUnavailable;
use App\Modules\Kennel\Exceptions\AdoptionUnavailable;
use App\Modules\Kennel\Exceptions\StarterBreedUnavailable;
use App\Modules\Kennel\Exceptions\StarterPetAlreadyClaimed;
use App\Modules\Pets\Exceptions\GameEventUnavailable;
use App\Modules\Pets\Exceptions\PendingGameEventRegistration;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Players\Enums\PlayerStatus;
use App\Modules\Players\Exceptions\InsufficientFunds;
use App\Modules\Players\Exceptions\WorkUnavailable;
use App\Modules\Telegram\Actions\ConnectTelegramAccount;
use App\Modules\Telegram\DTO\TelegramScreen;
use App\Modules\Telegram\DTO\TelegramUpdate;
use App\Modules\Telegram\Exceptions\TelegramUnavailable;
use DefStudio\Telegraph\Models\TelegraphBot;
use DefStudio\Telegraph\Models\TelegraphChat;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Throwable;

final class HandleTelegramUpdate
{
    public function __construct(
        private ConnectTelegramAccount $connect,
        private GameMenu $menu,
        private MenuMessenger $messenger,
        private RunTelegramGameCommand $game,
    ) {}

    public function handle(TelegraphBot $bot, TelegramUpdate $update): void
    {
        $chat = $bot->chats()->where('chat_id', $update->senderId)->first();
        $account = null;
        $removeIncomingMessage = ! $update->isCallback() && $update->command !== null;
        try {
            if ($update->command === 'start' && $update->parameter !== null) {
                $account = $this->connectAccount($bot, $chat, $update, $update->parameter);
                $chat = $account->chat;
            }
            $account = $chat === null ? null : TelegramAccount::query()->where('telegraph_chat_id', $chat->id)->where('telegram_user_id', $update->senderId)->first();
            if ($account === null) {
                if (! $update->isCallback() && $update->command !== null) {
                    $chat ??= $bot->chats()->make(['chat_id' => $update->senderId]);
                    $this->messenger->welcome($chat);
                }

                return;
            }
            $user = $account->user;
            if ($user->status !== PlayerStatus::Active) {
                return;
            }
            app()->setLocale($user->preferredLocale());
            $command = $this->resolveCommand($account, $update);
            if ($command !== null) {
                $removeIncomingMessage = ! $update->isCallback();
                $this->game->handle($account, $command);
            }
        } catch (TelegramUnavailable|PetUnavailable|GameEventUnavailable|ItemUnavailable|InsufficientFunds|WorkUnavailable|PendingGameEventRegistration|ModelNotFoundException|AdoptionUnavailable|StarterBreedUnavailable|StarterPetAlreadyClaimed $exception) {
            $chat ??= $bot->chats()->make(['chat_id' => $update->senderId]);
            $account ??= $chat->exists ? TelegramAccount::query()->where('telegraph_chat_id', $chat->id)->first() : null;
            $this->renderFailure($account, $chat, $exception);
        } finally {
            if ($removeIncomingMessage && $chat !== null && $update->messageId > 0) {
                $this->messenger->remove($chat, $update->messageId);
            }
        }
    }

    private function connectAccount(TelegraphBot $bot, ?TelegraphChat $chat, TelegramUpdate $update, string $token): TelegramAccount
    {
        return DB::transaction(function () use ($bot, $chat, $update, $token): TelegramAccount {
            $chat ??= $bot->chats()->create(['chat_id' => $update->senderId, 'name' => 'DogLive player']);

            return $this->connect->handle($bot, $chat, $update->senderId, $update->firstName, $update->username, $token);
        });
    }

    /** @return array<string, mixed>|null */
    private function resolveCommand(TelegramAccount $account, TelegramUpdate $update): ?array
    {
        if (! $update->isCallback()) {
            if ($update->command !== null) {
                return ['action' => $update->command === 'help' ? 'help' : 'home'];
            }
            foreach ([['marketSale', 'awaitingPrice', 'priceRequestedAt', 'market.sell.value'], ['kennel', 'awaitingName', 'nameRequestedAt', 'kennel.name']] as [$key, $flag, $timestamp, $action]) {
                $draft = $account->menu_state['data'][$key] ?? null;
                if ($update->text !== null && is_array($draft) && ($draft['userId'] ?? null) === $account->user_id
                    && ($draft[$flag] ?? false) === true && is_int($draft[$timestamp] ?? null)
                    && $draft[$timestamp] > 0 && $update->sentAt !== null && $update->sentAt >= $draft[$timestamp]) {
                    return ['action' => $action, 'text' => $update->text];
                }
            }

            return null;
        }
        $callback = $update->callback;
        if ($callback === null || $account->id !== $callback->accountId) {
            return null;
        }
        if ($account->menu_revision !== $callback->revision || $account->menu_message_id !== $update->messageId) {
            $home = $this->menu->render($account->user);
            $this->messenger->publish($account, new TelegramScreen(htmlspecialchars(__('telegram.errors.stale'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')."\n\n".$home->text, $home->rows));

            return null;
        }
        $command = $account->menu_state['commands'][$callback->key] ?? null;

        return is_array($command) ? $command : null;
    }

    private function renderFailure(?TelegramAccount $account, TelegraphChat $chat, Throwable $exception): void
    {
        $message = match (true) {
            $exception instanceof ModelNotFoundException => __('telegram.errors.stale'),
            $exception instanceof StarterBreedUnavailable => 'This breed is currently unavailable. Please choose another.',
            $exception instanceof StarterPetAlreadyClaimed => 'Your first dog is available only once, before you have any pets.',
            default => $exception->getMessage(),
        };
        $isKennelFailure = $exception instanceof AdoptionUnavailable || $exception instanceof StarterBreedUnavailable || $exception instanceof StarterPetAlreadyClaimed;
        $translated = $message !== '' && ($isKennelFailure || Lang::has($message)) ? __($message, [
            'pet_daily_limit' => (int) config('game-events.pet_daily_limit', 2),
            'pet_rest_hours' => (int) config('game-events.pet_rest_hours', 2),
        ]) : __('telegram.errors.unavailable_action');
        if ($account === null) {
            $this->messenger->welcome($chat, $translated);

            return;
        }
        $this->messenger->publish($account, new TelegramScreen(htmlspecialchars($translated, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), [[['text' => __('telegram.home'), 'command' => ['action' => 'home']]]]));
    }
}

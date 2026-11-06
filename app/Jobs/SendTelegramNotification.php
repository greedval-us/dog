<?php

namespace App\Jobs;

use App\Http\Telegram\TelegramDeliveryFailed;
use App\Http\Telegram\TelegramMessageSender;
use App\Models\TelegramAccount;
use App\Modules\Players\DTO\SystemNotificationData;
use App\Modules\Players\Enums\PlayerStatus;
use App\Modules\Players\Queries\GetSystemNotificationRecords;
use App\Modules\Telegram\Services\MenuSession;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class SendTelegramNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public int $accountId, public string $notificationId) {}

    public function handle(MenuSession $session, GetSystemNotificationRecords $notifications, TelegramMessageSender $sender): void
    {
        $account = TelegramAccount::query()->with('chat')->find($this->accountId);
        if ($account === null) {
            return;
        }

        $session->withLock($account->chat->telegraph_bot_id, $account->telegram_user_id, function () use ($account, $notifications, $sender): void {
            // Disconnecting uses the same chat lock. Recheck the original connection after acquiring it.
            $account = $account->fresh(['user', 'chat']);
            if ($account === null || $account->user->status !== PlayerStatus::Active || (string) $account->chat->chat_id !== $account->telegram_user_id) {
                return;
            }
            $record = $notifications->handle($account->user)->find($this->notificationId);
            if ($record === null || isset($record->data['telegramMessageId'])) {
                return;
            }

            $data = SystemNotificationData::fromModel($record);
            $locale = $account->user->preferredLocale();
            $title = $data->title[$locale] ?? $data->title['ru'];
            $body = $data->message[$locale] ?? $data->message['ru'];
            $request = $account->chat->html('<b>'.e($title)."</b>\n\n".e($body));
            $url = $this->siteUrl($data->actionUrl);
            if ($url !== null) {
                $request = $request->keyboard(Keyboard::make()->row([Button::make(__('telegram.alerts.open', [], $locale))->url($url)]));
            }

            $response = $sender->send($request);
            $id = $response->telegraphMessageId();
            if ($id !== null && $id > 0) {
                $record->update(['data' => [...$record->data, 'telegramMessageId' => $id]]);

                return;
            }
            $status = (int) $response->json('error_code', $response->status());
            if ($status === 429) {
                $this->release(max(1, (int) $response->json('parameters.retry_after', 30)));

                return;
            }
            if ($status >= 400 && $status < 500) {
                $this->fail(new TelegramDeliveryFailed(status: $status));

                return;
            }

            throw new TelegramDeliveryFailed(status: $status);
        });
    }

    private function siteUrl(?string $path): ?string
    {
        $site = config('app.url');
        if (! is_string($site) || ! str_starts_with($site, 'https://') || $path === null || ! str_starts_with($path, '/')
            || str_starts_with($path, '//') || str_contains($path, '\\') || preg_match('/[\x00-\x20\x7f]/', $path)) {
            return null;
        }

        return rtrim($site, '/').$path;
    }
}

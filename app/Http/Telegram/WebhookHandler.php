<?php

namespace App\Http\Telegram;

use App\Modules\Telegram\Services\MenuSession;
use DefStudio\Telegraph\Handlers\WebhookHandler as TelegraphWebhookHandler;
use DefStudio\Telegraph\Models\TelegraphBot;
use Illuminate\Http\Request;

final class WebhookHandler extends TelegraphWebhookHandler
{
    public function __construct(
        private TelegramUpdateParser $parser,
        private HandleTelegramUpdate $updates,
        private MenuMessenger $messenger,
        private MenuSession $menus,
    ) {
        parent::__construct();
    }

    public function handle(Request $request, TelegraphBot $bot): void
    {
        if (! $bot->exists) {
            return;
        }
        $payload = $request->isJson() ? json_decode($request->getContent(), true) : $request->all();
        if (! is_array($payload)) {
            return;
        }
        $update = $this->parser->parse($payload);
        if ($update === null) {
            return;
        }
        if ($update->isCallback()) {
            $this->messenger->send($bot->withEndpoint('answerCallbackQuery')->withData('callback_query_id', (string) $update->callbackQueryId));
            if ($update->callback === null) {
                return;
            }
        }
        $this->menus->withLock($bot->id, $update->senderId, fn () => $this->updates->handle($bot, $update));
    }

    protected function canHandle(string $action): bool
    {
        return false;
    }
}

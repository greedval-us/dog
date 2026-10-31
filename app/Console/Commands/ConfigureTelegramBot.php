<?php

namespace App\Console\Commands;

use App\Http\Telegram\MenuMessenger;
use App\Modules\Telegram\Queries\GetTelegramBotUrl;
use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Models\TelegraphBot;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\MultipleRecordsFoundException;

#[Signature('telegram:configure {bot? : Existing Telegraph bot database ID} {--register-webhook : Register the public HTTPS webhook and bot commands}')]
#[Description('Configure an existing DogLive Telegram bot from the database')]
class ConfigureTelegramBot extends Command
{
    public function handle(MenuMessenger $messenger, GetTelegramBotUrl $getBotUrl): int
    {
        $botId = $this->argument('bot');
        if ($botId !== null && filter_var($botId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $this->error('Provide a valid Telegraph bot database ID.');

            return self::FAILURE;
        }

        try {
            $bot = TelegraphBot::fromId($botId === null ? null : (int) $botId);
        } catch (ModelNotFoundException) {
            $this->error('No Telegraph bot was found. Create one with telegraph:new-bot or provide an existing database ID.');

            return self::FAILURE;
        } catch (MultipleRecordsFoundException) {
            $this->error('Several Telegraph bots are registered. Run telegram:configure with the intended bot database ID.');

            return self::FAILURE;
        }

        $secret = (string) config('telegraph.webhook.secret');
        if (! preg_match('/^[a-zA-Z0-9_-]{1,256}$/D', $secret)) {
            $this->error('Set a valid TELEGRAPH_WEBHOOK_SECRET in the environment.');

            return self::FAILURE;
        }
        if ($this->option('register-webhook')) {
            $domain = config('telegraph.webhook.domain') ?: config('app.url');
            if (! is_string($domain) || ! str_starts_with($domain, 'https://')) {
                $this->error('A public HTTPS APP_URL or TELEGRAM_WEBHOOK_DOMAIN is required.');

                return self::FAILURE;
            }
        }
        $botUrl = $getBotUrl->handle($bot, refresh: true);
        if ($botUrl === null) {
            $this->error('The registered bot could not be verified. Check its stored token and the Telegram connection.');

            return self::FAILURE;
        }
        if ($this->option('register-webhook')) {
            $response = $messenger->send(Telegraph::bot($bot)->registerWebhook(secretToken: $secret, allowedUpdates: ['message', 'callback_query']));
            if ($response->telegraphError()) {
                $this->error('Telegram did not accept the webhook. Check the public HTTPS endpoint.');

                return self::FAILURE;
            }
            $response = $messenger->send($bot->registerCommands([
                'menu' => 'DogLive: game menu / Игровое меню',
                'help' => 'Help / Помощь',
            ]));
            if ($response->telegraphError()) {
                $this->error('The webhook is registered, but the command menu could not be updated. Run the command again.');

                return self::FAILURE;
            }
        }
        $this->info('DogLive Telegram bot configured: @'.basename($botUrl));

        return self::SUCCESS;
    }
}

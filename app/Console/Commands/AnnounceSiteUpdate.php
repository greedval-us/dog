<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

#[Signature('notifications:announce
    {--title-ru= : Russian title}
    {--message-ru= : Russian announcement}
    {--title-en= : English title}
    {--message-en= : English announcement}')]
#[Description('Publish a site update to every player’s system notifications')]
class AnnounceSiteUpdate extends Command
{
    public function handle(): int
    {
        $data = [
            'title_ru' => trim((string) $this->option('title-ru')),
            'message_ru' => trim((string) $this->option('message-ru')),
            'title_en' => trim((string) $this->option('title-en')),
            'message_en' => trim((string) $this->option('message-en')),
        ];
        $validator = Validator::make($data, [
            'title_ru' => ['required', 'string', 'max:160'],
            'message_ru' => ['required', 'string', 'max:5000'],
            'title_en' => ['required', 'string', 'max:160'],
            'message_en' => ['required', 'string', 'max:5000'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $sent = 0;
        foreach (User::query()->select(['id', 'locale'])->lazyById(500) as $user) {
            $user->notify(SystemNotification::siteUpdate(
                ['ru' => $data['title_ru'], 'en' => $data['title_en']],
                ['ru' => $data['message_ru'], 'en' => $data['message_en']],
            ));
            $sent++;
        }

        $this->info("Announcement published to {$sent} players.");

        return self::SUCCESS;
    }
}

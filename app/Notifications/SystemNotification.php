<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

class SystemNotification extends Notification
{
    /**
     * @param  array{ru: string, en: string}  $title
     * @param  array{ru: string, en: string}  $message
     */
    private function __construct(
        private readonly string $kind,
        private readonly array $title,
        private readonly array $message,
        private readonly ?string $actionUrl = null,
    ) {}

    public static function login(?string $ipAddress): self
    {
        return self::translated('login', $ipAddress === null ? 'login.message' : 'login.message_with_ip', ['ip' => $ipAddress ?? '']);
    }

    public static function passwordChanged(): self
    {
        return self::translated('password_changed', 'password_changed.message');
    }

    public static function passwordReset(): self
    {
        return self::translated('password_reset', 'password_reset.message');
    }

    /**
     * @param  array{ru: string, en: string}  $title
     * @param  array{ru: string, en: string}  $message
     */
    public static function siteUpdate(array $title, array $message): self
    {
        return new self('site_update', $title, $message);
    }

    /**
     * @param  'achievement'|'pet_death'|'show_result'|'competition_result'|'breeding_success'|'pet_sold'  $kind
     * @param  array{ru: array<string, string>, en: array<string, string>}  $replacements
     */
    public static function gameplay(string $kind, array $replacements, string $actionUrl): self
    {
        return new self($kind, [
            'ru' => Lang::get("notifications.{$kind}.title", [], 'ru'),
            'en' => Lang::get("notifications.{$kind}.title", [], 'en'),
        ], [
            'ru' => Lang::get("notifications.{$kind}.message", $replacements['ru'], 'ru'),
            'en' => Lang::get("notifications.{$kind}.message", $replacements['en'], 'en'),
        ], $actionUrl);
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array{kind: string, title: array{ru: string, en: string}, message: array{ru: string, en: string}, actionUrl?: string} */
    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'message' => $this->message,
            ...($this->actionUrl === null ? [] : ['actionUrl' => $this->actionUrl]),
        ];
    }

    /**
     * @param  'login'|'password_changed'|'password_reset'  $kind
     * @param  array<string, string>  $replacements
     */
    private static function translated(string $kind, string $messageKey, array $replacements = []): self
    {
        return new self($kind, [
            'ru' => Lang::get("notifications.{$kind}.title", [], 'ru'),
            'en' => Lang::get("notifications.{$kind}.title", [], 'en'),
        ], [
            'ru' => Lang::get("notifications.{$messageKey}", $replacements, 'ru'),
            'en' => Lang::get("notifications.{$messageKey}", $replacements, 'en'),
        ]);
    }
}

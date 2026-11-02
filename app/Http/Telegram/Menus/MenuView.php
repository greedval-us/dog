<?php

namespace App\Http\Telegram\Menus;

use App\Models\User;
use App\Modules\Telegram\DTO\TelegramScreen;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * @phpstan-type Button array{text: string, command?: array<string, mixed>, url?: string}
 * @phpstan-type Command array<string, mixed>
 * @phpstan-type State array<string, mixed>
 */
final class MenuView
{
    public const PAGE_SIZE = 6;

    private const LABEL_ICONS = [
        'pets' => '🐾', 'profile' => '👤', 'kennel.title' => '🐕', 'slots.title' => '🏡',
        'inventory' => '🎒', 'shop' => '🛍', 'daily_work.title' => '🧰', 'work.title' => '💼',
        'event.competitions' => '🏆', 'event.exhibitions' => '🎖', 'notifications.title' => '🔔',
        'help' => '❔', 'website' => '🌐', 'back' => '←', 'home' => '🏠', 'refresh' => '↻',
        'care' => '💚', 'trainings' => '🏃', 'skill.title' => '🎓', 'clinic.title' => '🩺', 'history.title' => '📖',
        'start' => '▶', 'complete' => '✓', 'buy' => '🛒', 'confirm' => '✓', 'skip' => '↷',
        'daily_work.complete' => '✓', 'work.start' => '▶', 'work.complete' => '✓', 'skill.train' => '✓',
        'clinic.confirm' => '✓', 'event.register' => '✓', 'event.update' => '✓', 'event.continue' => '→',
        'kennel.adopt_free' => '🐾', 'kennel.buy' => '✓', 'kennel.rename' => '✎', 'kennel.open_pet' => '🐾',
        'slots.kennel' => '🐕', 'home_summary.ready_care' => '✓', 'home_summary.ready_work' => '✓',
        'home_summary.care_dog' => '💚', 'home_summary.pending_activity' => '⏳',
        'market.title' => '🐕', 'market.own' => '📋', 'market.sell_title' => '🏷', 'market.open_pet' => '🐾',
        'market.publish' => '✓', 'market.withdraw_action' => '✕', 'market.withdraw' => '✕', 'market.edit_price' => '✎',
    ];

    public function dateTime(string $value, User $user): string
    {
        return CarbonImmutable::parse($value)->setTimezone($user->timezone ?: config('app.timezone'))->format('d.m H:i T');
    }

    public function reason(string $reason, string $locale): string
    {
        return (string) trans($reason, [], $locale);
    }

    /** @param list<array<string, mixed>> $risks */
    public function riskText(array $risks, string $locale): string
    {
        $lines = [];
        foreach (array_slice($risks, 0, 4) as $risk) {
            $effect = $risk['effect'];
            $lines[] = $this->translate('risk', $locale).': '.($effect['name'][$locale] ?? $effect['name']['en'] ?? $effect['code']).' ('.($risk['chance'] / 100).'%)';
        }

        return $lines === [] ? '' : "\n".implode("\n", $lines);
    }

    /** @param array<string, string|int> $replace */
    public function translate(string $key, string $locale, array $replace = []): string
    {
        return (string) trans('telegram.'.$key, $replace, $locale);
    }

    /** @param array<string, string|int> $replace */
    public function label(string $key, string $locale, array $replace = []): string
    {
        $icon = self::LABEL_ICONS[$key] ?? null;

        return ($icon === null ? '' : $icon.' ').$this->translate($key, $locale, $replace);
    }

    public function heading(string $text): string
    {
        return '<b>'.$this->escape($text).'</b>';
    }

    public function bar(float $percentage): string
    {
        $filled = (int) round(max(0, min(100, $percentage)) / 20);

        return str_repeat('■', $filled).str_repeat('□', 5 - $filled);
    }

    public function escape(string $value): string
    {
        $escaped = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if (mb_strlen($escaped) <= 4000) {
            return $escaped;
        }

        return preg_replace('/&[^;]*$/u', '', mb_strcut($escaped, 0, 3990, 'UTF-8')).'…';
    }

    /**
     * @param  Command  $parameters
     * @return Button
     */
    public function button(string $label, string $action, string $locale, array $parameters = []): array
    {
        return $this->commandButton($this->label($label, $locale), ['action' => $action, ...$parameters]);
    }

    /**
     * @param  Command  $command
     * @return Button
     */
    public function commandButton(string $label, array $command): array
    {
        return ['text' => Str::limit($label, 48), 'command' => $command];
    }

    /** @return Button */
    public function website(string $locale): array
    {
        return ['text' => $this->label('website', $locale), 'url' => route('dashboard')];
    }

    /**
     * @param  Command  $command
     * @return list<list<Button>>
     */
    public function offsetNavigation(int $count, int $offset, array $command, string $locale): array
    {
        return $this->pageNavigation($count, $offset, null, null, null, $command, $locale);
    }

    /**
     * @param  Command  $command
     * @return list<list<Button>>
     */
    public function pageNavigation(int $count, int $offset, ?string $cursor, ?string $next, ?string $previous, array $command, string $locale, int $previousOffset = 0): array
    {
        $buttons = [];
        if ($offset > 0 || $previous !== null) {
            $buttons[] = $this->button('previous', (string) $command['action'], $locale, [...$command, 'offset' => $offset > 0 ? max(0, $offset - self::PAGE_SIZE) : $previousOffset, 'cursor' => $offset > 0 ? $cursor : $previous]);
        }
        if ($offset + self::PAGE_SIZE < $count || $next !== null) {
            $buttons[] = $this->button('next', (string) $command['action'], $locale, [...$command, 'offset' => $offset + self::PAGE_SIZE < $count ? $offset + self::PAGE_SIZE : 0, 'cursor' => $offset + self::PAGE_SIZE < $count ? $cursor : $next]);
        }

        return $buttons === [] ? [] : [$buttons];
    }

    /**
     * @param  list<list<Button>>  $rows
     * @param  Command  $back
     * @param  State  $state
     */
    public function screen(string $text, array $rows, string $locale, array $back = ['action' => 'home'], array $state = [], ?string $photoPath = null): TelegramScreen
    {
        $rows[] = [$this->button('back', (string) $back['action'], $locale, $back), $this->button('home', 'home', $locale)];
        $lines = explode("\n", $this->escape($text), 2);
        $formatted = '<b>'.$lines[0].'</b>'.(isset($lines[1]) ? "\n".$lines[1] : '');

        return new TelegramScreen($formatted, $rows, $state, $photoPath);
    }
}

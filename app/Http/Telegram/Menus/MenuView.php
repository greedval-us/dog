<?php

namespace App\Http\Telegram\Menus;

use App\Modules\Telegram\DTO\TelegramScreen;
use Illuminate\Support\Str;

/**
 * @phpstan-type Button array{text: string, command?: array<string, mixed>, url?: string}
 * @phpstan-type Command array<string, mixed>
 * @phpstan-type State array<string, mixed>
 */
final class MenuView
{
    public const PAGE_SIZE = 6;

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
        return $this->commandButton($this->translate($label, $locale), ['action' => $action, ...$parameters]);
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
        return ['text' => $this->translate('website', $locale), 'url' => route('dashboard')];
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
    public function pageNavigation(int $count, int $offset, ?string $cursor, ?string $next, ?string $previous, array $command, string $locale): array
    {
        $buttons = [];
        if ($offset > 0 || $previous !== null) {
            $buttons[] = $this->button('previous', (string) $command['action'], $locale, [...$command, 'offset' => max(0, $offset - self::PAGE_SIZE), 'cursor' => $offset > 0 ? $cursor : $previous]);
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
    public function screen(string $text, array $rows, string $locale, array $back = ['action' => 'home'], array $state = []): TelegramScreen
    {
        $rows[] = [$this->button('back', (string) $back['action'], $locale, $back), $this->button('home', 'home', $locale)];

        return new TelegramScreen($this->escape($text), $rows, $state);
    }
}

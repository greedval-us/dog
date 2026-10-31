<?php

namespace App\Modules\Telegram\DTO;

final readonly class TelegramScreen
{
    /**
     * @param  list<list<array{text: string, command?: array<string, mixed>, url?: string}>>  $rows
     * @param  array<string, mixed>  $state
     */
    public function __construct(public string $text, public array $rows, public array $state = []) {}
}

<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TelegramLinkTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $user_id
 * @property int $telegraph_bot_id
 * @property string $token_hash
 * @property CarbonImmutable $expires_at
 */
#[Fillable(['user_id', 'telegraph_bot_id', 'token_hash', 'expires_at'])]
#[Hidden(['token_hash'])]
class TelegramLinkToken extends Model
{
    /** @use HasFactory<TelegramLinkTokenFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime'];
    }
}

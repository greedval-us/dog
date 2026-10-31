<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TelegramAccountFactory;
use DefStudio\Telegraph\Models\TelegraphChat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $telegraph_chat_id
 * @property string $telegram_user_id
 * @property string $first_name
 * @property string|null $username
 * @property CarbonImmutable $linked_at
 * @property int|null $menu_message_id
 * @property array<string, mixed> $menu_state
 * @property int $menu_revision
 * @property-read User $user
 * @property-read TelegraphChat $chat
 */
#[Fillable(['user_id', 'telegraph_chat_id', 'telegram_user_id', 'first_name', 'username', 'linked_at', 'menu_message_id', 'menu_state', 'menu_revision'])]
class TelegramAccount extends Model
{
    /** @use HasFactory<TelegramAccountFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<TelegraphChat, $this> */
    public function chat(): BelongsTo
    {
        return $this->belongsTo(TelegraphChat::class, 'telegraph_chat_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['linked_at' => 'immutable_datetime', 'menu_state' => 'array', 'menu_message_id' => 'integer', 'menu_revision' => 'integer'];
    }
}

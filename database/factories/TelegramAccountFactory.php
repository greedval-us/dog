<?php

namespace Database\Factories;

use App\Models\TelegramAccount;
use App\Models\User;
use DefStudio\Telegraph\Models\TelegraphChat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TelegramAccount>
 */
class TelegramAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'telegraph_chat_id' => TelegraphChat::factory(),
            'telegram_user_id' => fn (array $attributes): string => (string) TelegraphChat::query()->whereKey($attributes['telegraph_chat_id'])->firstOrFail()->chat_id,
            'first_name' => fake()->firstName(),
            'username' => null,
            'linked_at' => now(),
            'menu_state' => [],
            'menu_revision' => 0,
        ];
    }
}

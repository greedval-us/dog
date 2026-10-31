<?php

namespace Database\Factories;

use App\Models\TelegramLinkToken;
use App\Models\User;
use DefStudio\Telegraph\Models\TelegraphBot;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TelegramLinkToken>
 */
class TelegramLinkTokenFactory extends Factory
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
            'telegraph_bot_id' => TelegraphBot::factory(),
            'token_hash' => hash('sha256', Str::random(48)),
            'expires_at' => now()->addMinutes(10),
        ];
    }
}

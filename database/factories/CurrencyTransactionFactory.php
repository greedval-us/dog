<?php

namespace Database\Factories;

use App\Models\CurrencyTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CurrencyTransaction>
 */
class CurrencyTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['coins' => 100]),
            'currency' => 'coins',
            'amount' => 100,
            'balance_before' => 0,
            'balance_after' => 100,
            'operation_key' => fake()->uuid(),
            'reason' => 'reward',
        ];
    }
}

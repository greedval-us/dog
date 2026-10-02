<?php

namespace Database\Factories;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AdminAuditLog> */
class AdminAuditLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'actor_name' => fake()->name(),
            'target_type' => User::class,
            'target_id' => User::factory(),
            'action' => 'updated',
            'reason' => fake()->sentence(),
            'changes' => ['status' => ['before' => 'active', 'after' => 'blocked']],
        ];
    }
}

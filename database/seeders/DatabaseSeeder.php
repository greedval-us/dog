<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([DogSeeder::class, CharacterTraitSeeder::class, GameAssetSeeder::class, ShopItemSeeder::class, ItemEffectRuleSeeder::class, WorkTypeSeeder::class, TrainingSeeder::class, DogWorkTypeSeeder::class, DiseaseSeeder::class, PetHistorySeeder::class, AchievementSeeder::class]);

        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}

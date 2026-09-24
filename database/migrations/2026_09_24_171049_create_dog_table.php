<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dog', function (Blueprint $table): void {
            $table->id();
            $table->string('breed', 64)->unique();
            $table->json('name');
            $table->json('description');
            $table->enum('size', ['small', 'medium', 'large']);
            $table->json('coat_colors');
            $table->boolean('is_starter')->default(false);

            foreach (['endurance', 'speed', 'strength', 'agility', 'obedience', 'intelligence'] as $stat) {
                $table->unsignedInteger($stat.'_potential');
            }

            foreach (['health', 'energy', 'satiety', 'hydration', 'mood', 'cleanliness', 'bond'] as $state) {
                $table->unsignedInteger($state.'_max');
            }

            $table->unsignedInteger('food_per_day')->comment('Game nutrition units per real day, not grams.');
            $table->unsignedInteger('water_per_day')->comment('Game hydration units per real day, not millilitres.');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dog');
    }
};

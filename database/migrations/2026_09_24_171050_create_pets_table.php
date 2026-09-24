<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('dog_id')->constrained('dog')->restrictOnDelete();
            $table->foreignId('father_id')->nullable()->constrained('pets')->restrictOnDelete();
            $table->foreignId('mother_id')->nullable()->constrained('pets')->restrictOnDelete();
            $table->string('name', 64);
            $table->enum('sex', ['male', 'female']);
            $table->string('coat_color', 64);
            $table->text('description')->nullable();
            $table->enum('size', ['small', 'medium', 'large']);
            $table->timestamp('born_at');
            $table->unsignedInteger('generation')->default(1);
            $table->boolean('is_purebred')->default(true);
            $table->boolean('is_favorite')->default(false);
            $table->timestamp('retired_at')->nullable();
            $table->string('image_path')->nullable();
            $table->json('photos')->nullable();
            $table->json('traits')->nullable();

            foreach (['endurance', 'speed', 'strength', 'agility', 'obedience', 'intelligence'] as $stat) {
                $table->unsignedInteger($stat)->default(0);
                $table->unsignedInteger($stat.'_potential');
            }

            foreach (['health', 'energy', 'satiety', 'hydration', 'mood', 'cleanliness', 'bond'] as $state) {
                $table->decimal($state, 12, 4);
                $table->unsignedInteger($state.'_max');
            }

            $table->unsignedInteger('food_per_day')->comment('Game nutrition units per real day, not grams.');
            $table->unsignedInteger('water_per_day')->comment('Game hydration units per real day, not millilitres.');
            $table->timestamp('state_updated_at');
            $table->string('activity', 64)->nullable();
            $table->timestamp('activity_started_at')->nullable();
            $table->timestamp('activity_ends_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'retired_at']);
            $table->index('dog_id');
            $table->index('father_id');
            $table->index('mother_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pets');
    }
};

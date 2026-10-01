<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dog_work_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->json('name');
            $table->json('description')->nullable();
            $table->foreignId('required_skill_id')->constrained('skills')->restrictOnDelete();
            $table->unsignedTinyInteger('required_skill_level');
            $table->unsignedInteger('coins_reward');
            $table->unsignedInteger('gems_reward')->default(0);
            $table->unsignedInteger('duration_seconds');
            $table->unsignedInteger('energy_cost')->default(0);
            $table->unsignedInteger('daily_limit');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
        Schema::create('dog_work_boards', function (Blueprint $table) {
            $table->id();
            $table->date('work_date')->unique();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });
        Schema::create('dog_work_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dog_work_board_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('dog_work_type_id');
            $table->string('code');
            $table->json('name');
            $table->json('description')->nullable();
            $table->unsignedBigInteger('required_skill_id');
            $table->json('required_skill_name');
            $table->unsignedTinyInteger('required_skill_level');
            $table->unsignedInteger('coins_reward');
            $table->unsignedInteger('gems_reward')->default(0);
            $table->unsignedInteger('duration_seconds');
            $table->unsignedInteger('energy_cost')->default(0);
            $table->unsignedInteger('daily_limit');
            $table->unsignedInteger('reserved_count')->default(0);
            $table->timestamps();
            $table->unique(['dog_work_board_id', 'dog_work_type_id']);
        });
        Schema::create('dog_work_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('pet_id');
            $table->string('pet_name');
            $table->foreignId('dog_work_offer_id')->constrained()->restrictOnDelete();
            $table->uuid('token');
            $table->uuid('activity_token')->unique();
            $table->json('name');
            $table->unsignedInteger('coins_reward');
            $table->unsignedInteger('gems_reward')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('ends_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'token']);
            $table->unique(['dog_work_offer_id', 'user_id']);
            $table->index(['user_id', 'completed_at']);
            $table->index(['pet_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dog_work_shifts');
        Schema::dropIfExists('dog_work_offers');
        Schema::dropIfExists('dog_work_boards');
        Schema::dropIfExists('dog_work_types');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->json('name');
            $table->json('description');
            $table->unsignedInteger('coins_reward');
            $table->unsignedInteger('gems_bonus')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('work_shifts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_type_id')->constrained()->restrictOnDelete();
            $table->date('worked_on');
            $table->unsignedTinyInteger('streak_day');
            $table->unsignedInteger('coins_reward');
            $table->unsignedInteger('gems_reward')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'worked_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_shifts');
        Schema::dropIfExists('work_types');
    }
};

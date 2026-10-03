<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('achievements', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->jsonb('name');
            $table->jsonb('description');
            $table->string('image_path');
            $table->jsonb('rules');
            $table->jsonb('rule_description');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('player_achievements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('achievement_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('progress')->default(0);
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'achievement_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_achievements');
        Schema::dropIfExists('achievements');
    }
};

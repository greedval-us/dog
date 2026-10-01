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
        Schema::table('skills', function (Blueprint $table): void {
            $table->json('levels')->nullable();
            $table->boolean('is_active')->default(true);
        });

        Schema::table('pet_skill', function (Blueprint $table): void {
            $table->timestamp('last_trained_at')->nullable();
            $table->timestamp('cooldown_until')->nullable();
        });

        Schema::create('pet_skill_lessons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('pet_id');
            $table->unsignedBigInteger('skill_id');
            $table->unsignedSmallInteger('level');
            $table->unsignedInteger('price_paid');
            $table->json('requirements');
            $table->uuid('token');
            $table->timestamp('trained_at');
            $table->timestamp('cooldown_until');
            $table->foreignId('currency_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'token']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pet_skill_lessons');
        Schema::table('pet_skill', function (Blueprint $table): void {
            $table->dropColumn(['last_trained_at', 'cooldown_until']);
        });
        Schema::table('skills', function (Blueprint $table): void {
            $table->dropColumn(['levels', 'is_active']);
        });
    }
};

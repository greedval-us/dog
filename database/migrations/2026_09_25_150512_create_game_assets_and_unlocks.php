<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_assets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('kind', 20);
            $table->json('name');
            $table->foreignId('dog_id')->nullable()->constrained('dog')->restrictOnDelete();
            $table->string('coat_color', 64)->nullable();
            $table->string('pose', 32)->nullable();
            $table->string('image_path');
            $table->string('icon_path');
            $table->string('currency', 10)->nullable();
            $table->unsignedInteger('price')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['kind', 'dog_id', 'coat_color', 'is_active']);
        });

        Schema::create('asset_unlocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_asset_id')->constrained()->restrictOnDelete();
            $table->string('currency', 10);
            $table->unsignedInteger('price_paid');
            $table->timestamps();
            $table->unique(['user_id', 'game_asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_unlocks');
        Schema::dropIfExists('game_assets');
    }
};

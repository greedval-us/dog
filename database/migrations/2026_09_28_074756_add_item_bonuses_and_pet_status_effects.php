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
        Schema::create('status_effects', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('kind', 16);
            $table->json('name');
            $table->json('description');
            $table->json('modifiers');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('condition_state', 32)->nullable();
            $table->unsignedTinyInteger('condition_below')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        foreach (['items', 'inventory_items'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->json('bonuses')->nullable();
                $table->json('granted_effects')->nullable();
            });
        }

        Schema::table('pets', function (Blueprint $table): void {
            $table->json('buffs')->nullable();
            $table->json('debuffs')->nullable();
        });
        Schema::table('pet_care_actions', function (Blueprint $table): void {
            $table->json('granted_effects')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pet_care_actions', fn (Blueprint $table) => $table->dropColumn('granted_effects'));
        Schema::table('pets', fn (Blueprint $table) => $table->dropColumn(['buffs', 'debuffs']));
        foreach (['items', 'inventory_items'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['bonuses', 'granted_effects']));
        }
        Schema::dropIfExists('status_effects');
    }
};

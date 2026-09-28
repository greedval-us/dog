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
        Schema::table('status_effects', function (Blueprint $table): void {
            $table->json('risk_categories')->nullable();
            $table->json('risk_chance_by_quality')->nullable();
        });
        Schema::table('pet_care_actions', function (Blueprint $table): void {
            $table->json('incidents')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pet_care_actions', fn (Blueprint $table) => $table->dropColumn('incidents'));
        Schema::table('status_effects', fn (Blueprint $table) => $table->dropColumn(['risk_categories', 'risk_chance_by_quality']));
    }
};

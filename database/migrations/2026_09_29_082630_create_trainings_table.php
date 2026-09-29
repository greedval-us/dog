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
        Schema::create('trainings', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->json('name');
            $table->json('stat_gains');
            $table->json('state_costs');
            $table->unsignedInteger('energy_cost');
            $table->unsignedInteger('duration_seconds');
            $table->unsignedInteger('cooldown_seconds');
            $table->foreignId('status_effect_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('risk_chance')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trainings');
    }
};

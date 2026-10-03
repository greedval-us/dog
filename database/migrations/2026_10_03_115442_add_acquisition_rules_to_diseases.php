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
        Schema::table('diseases', function (Blueprint $table): void {
            $table->json('acquisition_rules')->nullable();
            $table->json('modifiers')->nullable();
            $table->boolean('is_active')->default(false);
        });

        Schema::table('pet_diseases', function (Blueprint $table): void {
            $table->json('effect_snapshot')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pet_diseases', function (Blueprint $table): void {
            $table->dropColumn('effect_snapshot');
        });

        Schema::table('diseases', function (Blueprint $table): void {
            $table->dropColumn(['acquisition_rules', 'modifiers', 'is_active']);
        });
    }
};

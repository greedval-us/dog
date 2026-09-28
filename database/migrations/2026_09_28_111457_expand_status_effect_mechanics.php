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
            $table->json('conditions')->nullable();
            $table->string('condition_group', 64)->nullable();
            $table->unsignedTinyInteger('condition_priority')->default(0);
            $table->json('care_variants')->nullable();
            $table->json('recovery_actions')->nullable();
        });

        Schema::table('pet_care_actions', function (Blueprint $table): void {
            $table->json('status_recovery')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pet_care_actions', fn (Blueprint $table) => $table->dropColumn('status_recovery'));
        Schema::table('status_effects', fn (Blueprint $table) => $table->dropColumn([
            'conditions', 'condition_group', 'condition_priority', 'care_variants', 'recovery_actions',
        ]));
    }
};

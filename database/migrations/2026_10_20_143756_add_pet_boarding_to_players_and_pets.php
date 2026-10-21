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
        Schema::table('users', function (Blueprint $table): void {
            $table->string('boarding_plan', 16)->default('off');
            $table->timestamp('boarding_enabled_at')->nullable();
            $table->json('boarding_absences')->nullable();
        });
        Schema::table('pets', function (Blueprint $table): void {
            $table->json('boarding_periods')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table): void {
            $table->dropColumn('boarding_periods');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['boarding_plan', 'boarding_enabled_at', 'boarding_absences']);
        });
    }
};

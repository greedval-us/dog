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
            $table->unsignedInteger('level')->default(1);
            $table->unsignedBigInteger('exhibition_wins')->default(0);
            $table->unsignedBigInteger('competition_wins')->default(0);
            $table->unsignedBigInteger('walks_count')->default(0);
            $table->unsignedBigInteger('trainings_count')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['level', 'exhibition_wins', 'competition_wins', 'walks_count', 'trainings_count']);
        });
    }
};

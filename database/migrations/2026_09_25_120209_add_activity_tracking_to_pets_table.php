<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pets', function (Blueprint $table): void {
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('stats_updated_at')->nullable();
            $table->uuid('activity_token')->nullable();
        });

        DB::table('pets')->update(['stats_updated_at' => DB::raw('state_updated_at')]);

        DB::table('pets')->whereNotNull('activity')->orderBy('id')->eachById(function (object $pet): void {
            DB::table('pets')->where('id', $pet->id)->update([
                'activity_token' => (string) Str::uuid(),
                'last_activity_at' => $pet->activity_started_at,
            ]);
        });

        Schema::table('pets', function (Blueprint $table): void {
            $table->timestamp('stats_updated_at')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table): void {
            $table->dropColumn(['last_activity_at', 'stats_updated_at', 'activity_token']);
        });
    }
};

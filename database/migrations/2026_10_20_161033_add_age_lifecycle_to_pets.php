<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            $table->unsignedSmallInteger('lifespan_months')->nullable();
            $table->timestamp('surrendered_at')->nullable();
        });

        $at = now();
        DB::table('pets')->whereNotNull('retired_at')->whereNull('died_at')->where('health', '>', 0)
            ->update(['state_updated_at' => $at, 'stats_updated_at' => $at]);
        DB::table('pets')->orderBy('id')->chunkById(500, function ($pets): void {
            foreach ($pets as $pet) {
                $range = match ($pet->size) {
                    'large' => [144, 168],
                    'small' => [168, 192],
                    default => [156, 180],
                };
                $lifespan = $range[0] + (int) (hexdec(substr(hash('sha256', 'legacy:'.$pet->id), 0, 8)) % ($range[1] - $range[0] + 1));
                DB::table('pets')->where('id', $pet->id)->update(['lifespan_months' => $lifespan]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            $table->dropColumn(['lifespan_months', 'surrendered_at']);
        });
    }
};

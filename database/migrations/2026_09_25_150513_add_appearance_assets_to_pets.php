<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('pets')->whereNotNull('image_path')->orWhere(function (Builder $query): void {
            $query->whereNotNull('photos')->whereRaw('CAST(photos AS TEXT) NOT IN (?, ?)', ['[]', 'null']);
        })->exists()) {
            throw new RuntimeException('Migrate existing pet image_path/photos into the asset catalogue before removing these columns.');
        }

        Schema::table('pets', function (Blueprint $table) {
            $table->foreignId('portrait_asset_id')->nullable()->constrained('game_assets')->nullOnDelete();
            $table->foreignId('background_asset_id')->nullable()->constrained('game_assets')->nullOnDelete();
            $table->dropColumn(['image_path', 'photos']);
        });
    }

    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('portrait_asset_id');
            $table->dropConstrainedForeignId('background_asset_id');
            $table->string('image_path')->nullable();
            $table->json('photos')->nullable();
        });
    }
};

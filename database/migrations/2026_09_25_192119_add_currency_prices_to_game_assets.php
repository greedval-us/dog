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
        $invalidPrice = DB::table('game_assets')->where(function (Builder $query): void {
            $query->where('price', '<', 0)
                ->orWhere(fn (Builder $query): Builder => $query->where('price', 0)->whereNotNull('currency'))
                ->orWhere(fn (Builder $query): Builder => $query->where('price', '>', 0)->where(function (Builder $query): void {
                    $query->whereNull('currency')->orWhereNotIn('currency', ['coins', 'gems']);
                }));
        })->exists();

        if ($invalidPrice) {
            throw new RuntimeException('Correct invalid game asset prices before migrating.');
        }

        Schema::table('game_assets', function (Blueprint $table) {
            $table->unsignedInteger('coins_price')->nullable();
            $table->unsignedInteger('gems_price')->nullable();
        });

        DB::table('game_assets')->where('currency', 'coins')->update([
            'coins_price' => DB::raw('price'), 'gems_price' => 10,
        ]);
        DB::table('game_assets')->where('currency', 'gems')->update([
            'coins_price' => 100, 'gems_price' => DB::raw('price'),
        ]);

        Schema::table('game_assets', function (Blueprint $table) {
            $table->dropColumn(['currency', 'price']);
        });
    }

    public function down(): void
    {
        if (DB::table('game_assets')->whereNotNull('coins_price')->whereNotNull('gems_price')->exists()) {
            throw new RuntimeException('Cannot roll back two asset prices into one without losing data. Keep one currency per asset before rolling back.');
        }

        Schema::table('game_assets', function (Blueprint $table) {
            $table->string('currency', 10)->nullable();
            $table->unsignedInteger('price')->default(0);
        });

        foreach (['coins', 'gems'] as $currency) {
            DB::table('game_assets')->whereNotNull($currency.'_price')->update([
                'currency' => $currency, 'price' => DB::raw($currency.'_price'),
            ]);
        }

        Schema::table('game_assets', function (Blueprint $table) {
            $table->dropColumn(['coins_price', 'gems_price']);
        });
    }
};

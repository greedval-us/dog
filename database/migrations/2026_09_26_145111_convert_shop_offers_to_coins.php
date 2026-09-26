<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            if (DB::table('shop_offers')->where('currency', 'gems')->where('price', '>', intdiv(PHP_INT_MAX, 10))->exists()) {
                throw new RuntimeException('Correct shop prices that exceed the supported coin conversion range.');
            }

            DB::table('shop_offers')->where('currency', 'gems')->update([
                'currency' => 'coins',
                'price' => DB::raw('price * 10'),
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('Original offer currencies cannot be recovered from converted coin prices. Use a forward migration to change pricing.');
    }
};

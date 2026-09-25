<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedTinyInteger('pet_slots')->default(1);
        });

        DB::table('pets')->select('user_id')->selectRaw('COUNT(*) as total')
            ->whereNotNull('user_id')->groupBy('user_id')->havingRaw('COUNT(*) > 1')
            ->orderBy('user_id')->each(function (object $owner): void {
                DB::table('users')->where('id', $owner->user_id)
                    ->update(['pet_slots' => min(9, (int) $owner->total)]);
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('pet_slots');
        });
    }
};

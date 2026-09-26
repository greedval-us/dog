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
        DB::table('dog')->where('energy_max', '<>', 100)->update(['energy_max' => 100]);

        DB::table('pets')->where('energy_max', '<>', 100)->orWhere('energy', '>', 100)->update([
            'energy_max' => 100,
            'energy' => DB::raw('CASE WHEN energy > 100 THEN 100 ELSE energy END'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('Previous energy capacities and capped energy cannot be restored. Use a forward migration to change the balance.');
    }
};

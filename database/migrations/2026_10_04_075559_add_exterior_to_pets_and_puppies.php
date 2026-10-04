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
        foreach (['pets', 'puppies'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->jsonb('exterior')->default('{"type":75,"structure":75,"movement":75}');
            });
            $columns = $tableName === 'puppies' ? ['id', 'dog_id', 'pet_id'] : ['id', 'dog_id'];
            DB::table($tableName)->select($columns)->orderBy('id')->chunkById(200, function ($rows) use ($tableName): void {
                $placed = $tableName === 'puppies' ? DB::table('pets')->whereIn('id', $rows->pluck('pet_id')->filter())->pluck('exterior', 'id') : collect();
                foreach ($rows as $row) {
                    if ($tableName === 'puppies' && isset($placed[$row->pet_id])) {
                        DB::table($tableName)->where('id', $row->id)->update(['exterior' => $placed[$row->pet_id]]);

                        continue;
                    }
                    $hash = hash('sha256', $tableName.':'.$row->id.':'.$row->dog_id);
                    $exterior = [];
                    foreach (['type', 'structure', 'movement'] as $index => $quality) {
                        $exterior[$quality] = 65 + hexdec(substr($hash, $index * 2, 2)) % 26;
                    }
                    DB::table($tableName)->where('id', $row->id)->update(['exterior' => json_encode($exterior, JSON_THROW_ON_ERROR)]);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['pets', 'puppies'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('exterior');
            });
        }
    }
};

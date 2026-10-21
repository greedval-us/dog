<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('pets')->whereNotNull('retired_at')->whereExists(function (Builder $query): void {
            $query->selectRaw('1')->from('pet_history_entries')
                ->whereColumn('pet_history_entries.pet_id', 'pets.id')
                ->where('pet_history_entries.event_code', 'life.retirement')
                ->where('pet_history_entries.details->automatic', true)
                ->whereColumn('pet_history_entries.occurred_at', 'pets.retired_at');
        })->update(['retired_at' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restoring a forced career flag does not retain enough information to recreate it on rollback.
    }
};

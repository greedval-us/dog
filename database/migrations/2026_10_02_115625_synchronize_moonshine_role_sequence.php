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
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('moonshine_user_roles', 'id'), COALESCE(MAX(id), 1), MAX(id) IS NOT NULL) FROM moonshine_user_roles");
        }
    }

    /** Sequence values reflect existing rows and are not rolled back. */
    public function down(): void {}
};

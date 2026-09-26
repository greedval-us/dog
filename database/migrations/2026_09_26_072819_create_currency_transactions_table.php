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
        Schema::create('currency_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('currency', 10);
            $table->bigInteger('amount');
            $table->bigInteger('balance_before');
            $table->bigInteger('balance_after');
            $table->string('operation_key', 128);
            $table->string('reason', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'operation_key']);
            $table->index(['user_id', 'id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE currency_transactions ADD CONSTRAINT currency_transactions_valid_amount CHECK (currency IN ('coins', 'gems') AND amount <> 0 AND balance_before >= 0 AND balance_after >= 0 AND balance_after - balance_before = amount)");
            DB::statement('ALTER TABLE users ADD CONSTRAINT users_nonnegative_balances CHECK (coins >= 0 AND gems >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT users_nonnegative_balances');
        }

        Schema::dropIfExists('currency_transactions');
    }
};

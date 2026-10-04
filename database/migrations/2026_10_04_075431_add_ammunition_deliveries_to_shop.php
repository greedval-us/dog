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
        Schema::table('shop_offers', function (Blueprint $table): void {
            $table->unsignedInteger('restock_interval_hours')->nullable();
            $table->unsignedInteger('restock_target')->nullable();
            $table->unsignedInteger('purchase_limit')->nullable();
            $table->timestamp('next_restock_at')->nullable();
            $table->timestamp('last_restock_at')->nullable();
            $table->index(['is_active', 'next_restock_at', 'id']);
        });
        Schema::create('shop_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_offer_id')->constrained()->restrictOnDelete();
            $table->timestamp('scheduled_at');
            $table->unsignedInteger('stock_before');
            $table->unsignedInteger('stock_after');
            $table->timestamps();
            $table->unique(['shop_offer_id', 'scheduled_at']);
        });
        Schema::table('item_purchases', function (Blueprint $table): void {
            $table->foreignId('shop_delivery_id')->nullable()->constrained()->restrictOnDelete();
            $table->index(['user_id', 'shop_offer_id', 'shop_delivery_id']);
        });
        DB::statement('ALTER TABLE shop_offers ADD CONSTRAINT shop_offers_valid_restock CHECK ((restock_interval_hours IS NULL AND restock_target IS NULL AND purchase_limit IS NULL AND next_restock_at IS NULL AND last_restock_at IS NULL) OR (restock_interval_hours IS NOT NULL AND restock_target IS NOT NULL AND purchase_limit IS NOT NULL AND restock_interval_hours IN (6, 168) AND restock_target BETWEEN 1 AND 2147483647 AND purchase_limit BETWEEN 1 AND 3 AND stock IS NOT NULL AND next_restock_at IS NOT NULL AND last_restock_at IS NOT NULL))');
        DB::statement('ALTER TABLE shop_deliveries ADD CONSTRAINT shop_deliveries_valid_stock CHECK (stock_before >= 0 AND stock_after BETWEEN 1 AND 2147483647)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('item_purchases', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'shop_offer_id', 'shop_delivery_id']);
            $table->dropConstrainedForeignId('shop_delivery_id');
        });
        Schema::dropIfExists('shop_deliveries');
        DB::statement('ALTER TABLE shop_offers DROP CONSTRAINT IF EXISTS shop_offers_valid_restock');
        Schema::table('shop_offers', function (Blueprint $table): void {
            $table->dropIndex(['is_active', 'next_restock_at', 'id']);
            $table->dropColumn(['restock_interval_hours', 'restock_target', 'purchase_limit', 'next_restock_at', 'last_restock_at']);
        });
    }
};

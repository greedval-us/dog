<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->json('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_category_id')->constrained()->restrictOnDelete();
            $table->string('code', 128)->unique();
            $table->json('name');
            $table->json('description')->nullable();
            $table->unsignedTinyInteger('quality');
            $table->unsignedInteger('usage_limit');
            $table->json('characteristics');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['item_category_id', 'is_active', 'id']);
        });

        Schema::create('shop_offers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->string('currency', 10);
            $table->unsignedBigInteger('price');
            $table->unsignedInteger('stock')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['is_active', 'sort_order', 'id']);
            $table->index('item_id');
        });

        Schema::create('item_purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shop_offer_id')->constrained()->restrictOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('currency_transaction_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->uuid('token');
            $table->string('currency', 10);
            $table->unsignedBigInteger('price_paid');
            $table->json('item_snapshot');
            $table->timestamps();
            $table->unique(['user_id', 'token']);
            $table->index(['user_id', 'id']);
            $table->index('shop_offer_id');
            $table->index('item_id');
        });

        Schema::create('inventory_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('item_purchase_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->json('name');
            $table->unsignedTinyInteger('quality');
            $table->unsignedInteger('usage_limit');
            $table->unsignedInteger('remaining_uses');
            $table->json('characteristics');
            $table->timestamps();
            $table->index(['user_id', 'id']);
            $table->index(['user_id', 'item_id', 'id']);
            $table->index('item_id');
        });

        Schema::create('item_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            /** The instance identity survives destruction; intentionally no foreign key. */
            $table->unsignedBigInteger('inventory_item_id');
            $table->uuid('token');
            $table->unsignedInteger('uses_spent');
            $table->unsignedInteger('uses_before');
            $table->unsignedInteger('uses_after');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'token']);
            $table->index(['user_id', 'id']);
            $table->index(['inventory_item_id', 'id']);
            $table->index('item_id');
        });

        $this->addCheck('items', 'quality BETWEEN 1 AND 10 AND usage_limit BETWEEN 1 AND 2147483647');
        $this->addCheck('inventory_items', 'quality BETWEEN 1 AND 10 AND usage_limit BETWEEN 1 AND 2147483647 AND remaining_uses BETWEEN 1 AND usage_limit');
        $this->addCheck('shop_offers', "currency IN ('coins', 'gems') AND price > 0 AND (stock IS NULL OR stock BETWEEN 0 AND 2147483647)");
        $this->addCheck('item_purchases', "currency IN ('coins', 'gems') AND price_paid > 0");
        $this->addCheck('item_usages', 'uses_spent > 0 AND uses_before > 0 AND uses_after >= 0 AND uses_before - uses_spent = uses_after');
    }

    public function down(): void
    {
        Schema::dropIfExists('item_usages');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('item_purchases');
        Schema::dropIfExists('shop_offers');
        Schema::dropIfExists('items');
        Schema::dropIfExists('item_categories');
    }

    /** SQLite cannot add CHECK constraints to existing table definitions. */
    private function addCheck(string $table, string $expression): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $columns = ['quality', 'usage_limit', 'remaining_uses', 'currency', 'price', 'stock', 'price_paid', 'uses_spent', 'uses_before', 'uses_after'];
            $expression = preg_replace('/\\b('.implode('|', $columns).')\\b/', 'NEW.$1', $expression);

            foreach (['insert', 'update'] as $event) {
                DB::statement("CREATE TRIGGER {$table}_valid_values_{$event} BEFORE {$event} ON {$table} WHEN NOT ({$expression}) BEGIN SELECT RAISE(ABORT, 'Invalid {$table} values'); END");
            }

            return;
        }

        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_valid_values CHECK ({$expression})");
    }
};

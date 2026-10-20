<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kennel_purchases', function (Blueprint $table): void {
            $table->unsignedSmallInteger('age_months')->default(0);
        });
        Schema::create('pet_sale_listings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pet_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('price');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'id']);
        });
        Schema::create('pet_sale_purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('listing_id')->constrained('pet_sale_listings')->restrictOnDelete();
            $table->foreignId('pet_id')->constrained()->restrictOnDelete();
            $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('token');
            $table->unsignedInteger('price_paid');
            $table->timestamps();
            $table->unique(['buyer_id', 'token']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_sale_purchases');
        Schema::dropIfExists('pet_sale_listings');
        Schema::table('kennel_purchases', function (Blueprint $table): void {
            $table->dropColumn('age_months');
        });
    }
};

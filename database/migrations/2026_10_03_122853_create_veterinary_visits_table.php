<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('veterinary_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('pet_id');
            $table->string('pet_name', 64);
            $table->string('service', 24);
            $table->unsignedBigInteger('disease_episode_id')->nullable();
            $table->json('disease_name')->nullable();
            $table->uuid('token');
            $table->unsignedInteger('price_paid');
            $table->foreignId('currency_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('performed_at');
            $table->timestamp('available_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'token']);
            $table->index(['pet_id', 'service', 'available_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('veterinary_visits');
    }
};

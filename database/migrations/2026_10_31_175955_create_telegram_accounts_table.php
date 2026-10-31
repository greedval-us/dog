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
        Schema::create('telegram_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('telegraph_chat_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('telegram_user_id')->unique();
            $table->string('first_name');
            $table->string('username')->nullable();
            $table->timestamp('linked_at');
            $table->unsignedBigInteger('menu_message_id')->nullable();
            $table->json('menu_state')->default('{}');
            $table->unsignedInteger('menu_revision')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_accounts');
    }
};

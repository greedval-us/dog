<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pet_care_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->uuid('token');
            $table->uuid('activity_token');
            $table->string('group', 16);
            $table->string('variant', 32);
            $table->json('inventory_item_ids');
            $table->json('effects');
            $table->timestamp('ends_at');
            $table->timestamp('available_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'token']);
            $table->index(['pet_id', 'group', 'available_at']);
            $table->index(['pet_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_care_actions');
    }
};

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
        Schema::create('pet_history_events', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->enum('kind', ['action', 'thought']);
            $table->json('name');
            $table->json('conditions')->nullable();
            $table->unsignedInteger('cooldown_minutes')->default(120);
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('pet_history_phrases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_history_event_id')->constrained()->cascadeOnDelete();
            $table->json('text');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['pet_history_event_id', 'sort_order']);
        });
        Schema::create('pet_history_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pet_history_event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pet_history_phrase_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('kind', ['action', 'thought']);
            $table->string('event_code', 80);
            $table->string('source_key', 160);
            $table->json('title');
            $table->json('message')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->unique(['pet_id', 'source_key']);
            $table->index(['pet_id', 'kind', 'occurred_at', 'id'], 'pet_history_feed_index');
            $table->index('occurred_at');
        });
        Schema::create('pet_thought_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pet_history_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pet_history_phrase_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('last_occurred_at');
            $table->timestamps();
            $table->unique(['pet_id', 'pet_history_event_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pet_thought_states');
        Schema::dropIfExists('pet_history_entries');
        Schema::dropIfExists('pet_history_phrases');
        Schema::dropIfExists('pet_history_events');
    }
};

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
        Schema::create('pet_disease_counters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('disease_id')->constrained()->restrictOnDelete();
            $table->date('tracked_on');
            $table->unsignedInteger('action_count')->default(0);
            $table->unsignedInteger('threshold');
            $table->timestamps();
            $table->unique(['pet_id', 'disease_id']);
            $table->index('disease_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pet_disease_counters');
    }
};

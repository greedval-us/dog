<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_events', function (Blueprint $table) {
            $table->id();
            $table->string('discipline', 32);
            $table->string('frequency', 16);
            $table->string('status', 16)->default('registration');
            $table->timestamp('registration_opens_at');
            $table->timestamp('closes_at');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('settled_at')->nullable();
            $table->string('seed', 64);
            $table->json('rules');
            $table->timestamps();
            $table->unique(['discipline', 'frequency', 'starts_at']);
            $table->index(['status', 'closes_at']);
            $table->index(['status', 'ends_at']);
        });
        Schema::create('game_event_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_event_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pet_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('is_npc')->default(false);
            $table->uuid('operation_token')->unique();
            $table->string('registration_hash', 64);
            $table->string('division', 128);
            $table->string('status', 16)->default('registered');
            $table->unsignedInteger('fee')->default(0);
            $table->json('plan');
            $table->json('gear_ids');
            $table->json('snapshot')->nullable();
            $table->json('result')->nullable();
            $table->unsignedTinyInteger('rank')->nullable();
            $table->unsignedInteger('prize')->default(0);
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('experience_awarded')->nullable();
            $table->timestamps();
            $table->unique(['game_event_id', 'user_id']);
            $table->unique(['game_event_id', 'pet_id']);
            $table->index(['pet_id', 'status']);
            $table->index(['game_event_id', 'division', 'status']);
            $table->index(['user_id', 'status']);
        });
        Schema::create('pet_sport_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_id')->constrained()->restrictOnDelete();
            $table->string('discipline', 32);
            $table->unsignedInteger('starts')->default(0);
            $table->unsignedInteger('wins')->default(0);
            $table->unsignedInteger('experience')->default(0);
            $table->unsignedTinyInteger('tier')->default(0);
            $table->timestamps();
            $table->unique(['pet_id', 'discipline']);
        });
        Schema::create('pet_titles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_id')->constrained()->restrictOnDelete();
            $table->foreignId('game_event_entry_id')->constrained()->restrictOnDelete();
            $table->string('discipline', 32);
            $table->string('frequency', 16);
            $table->string('code', 64);
            $table->timestamp('awarded_at');
            $table->timestamps();
            $table->unique('game_event_entry_id');
            $table->index(['pet_id', 'discipline']);
        });
        DB::statement('ALTER TABLE game_events ADD CONSTRAINT game_events_schedule_check CHECK (registration_opens_at < closes_at AND closes_at < starts_at AND starts_at < ends_at)');
        DB::statement("ALTER TABLE game_events ADD CONSTRAINT game_events_status_check CHECK (status IN ('registration','frozen','settled','cancelled'))");
        DB::statement("ALTER TABLE game_events ADD CONSTRAINT game_events_discipline_check CHECK (discipline IN ('agility','nosework','canicross','conformation','progeny'))");
        DB::statement("ALTER TABLE game_events ADD CONSTRAINT game_events_frequency_check CHECK (frequency IN ('daily','weekly','monthly'))");
        DB::statement("ALTER TABLE game_event_entries ADD CONSTRAINT game_entries_status_check CHECK (status IN ('registered','frozen','completed','cancelled','withdrawn'))");
        DB::statement('ALTER TABLE game_event_entries ADD CONSTRAINT game_entries_identity_check CHECK ((is_npc AND user_id IS NULL AND pet_id IS NULL AND fee = 0 AND prize = 0) OR (NOT is_npc AND pet_id IS NOT NULL))');
        DB::statement('ALTER TABLE game_event_entries ADD CONSTRAINT game_entries_money_check CHECK (fee >= 0 AND prize >= 0 AND (rank IS NULL OR rank BETWEEN 1 AND 8))');
        DB::statement('ALTER TABLE pet_sport_records ADD CONSTRAINT pet_sport_record_check CHECK (starts >= 0 AND wins >= 0 AND wins <= starts AND experience >= 0 AND tier BETWEEN 0 AND 2)');
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_titles');
        Schema::dropIfExists('pet_sport_records');
        Schema::dropIfExists('game_event_entries');
        Schema::dropIfExists('game_events');
    }
};

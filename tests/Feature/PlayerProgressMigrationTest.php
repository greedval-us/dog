<?php

use App\Models\DogWorkOffer;
use App\Models\DogWorkShift;
use App\Models\Pet;
use App\Models\PetCareAction;
use App\Models\PetSkillLesson;
use App\Models\User;
use App\Models\VeterinaryVisit;
use Carbon\CarbonImmutable;

test('progress migration preserves experience and restores lifetime statistics from completed legacy receipts', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-03 12:00:00', 'UTC'));
    config(['doglive.work_timezone' => 'Europe/Moscow']);
    $migration = require database_path('migrations/2026_10_03_144445_add_player_progress_to_users_and_pet_receipts.php');
    $migration->down();
    $owner = User::factory()->create([
        'experience' => '350', 'level' => 99, 'walks_count' => 7,
        'trainings_count' => 0, 'bio' => 'Люблю прогулки с собакой.',
    ]);
    $inactive = User::factory()->create(['experience' => '700', 'level' => 1]);
    $pet = Pet::factory()->for($owner)->create();
    $care = PetCareAction::factory()->count(6)->sequence(
        ['group' => 'feed', 'variant' => 'meal', 'completed_at' => '2026-10-01 20:55:00'],
        ['group' => 'feed', 'variant' => 'meal', 'completed_at' => '2026-10-01 20:57:00'],
        ['group' => 'feed', 'variant' => 'water', 'completed_at' => '2026-10-01 21:05:00'],
        ['group' => 'walk', 'variant' => 'walk', 'completed_at' => '2026-10-01 23:55:00'],
        ['group' => 'walk', 'variant' => 'home', 'completed_at' => '2026-10-02 00:05:00'],
        ['group' => 'training', 'variant' => 'training:42', 'completed_at' => '2026-10-02 00:30:00'],
    )->create([
        'user_id' => $owner->id, 'pet_id' => $pet->id,
        'ends_at' => '2026-10-01 20:50:00', 'available_at' => '2026-10-01 20:55:00',
    ]);
    $pendingCare = PetCareAction::factory()->create([
        'user_id' => $owner->id, 'pet_id' => $pet->id,
    ]);
    $work = DogWorkShift::factory()->create([
        'user_id' => $owner->id, 'pet_id' => $pet->id,
        'started_at' => '2026-10-02 20:40:00', 'ends_at' => '2026-10-02 21:10:00',
        'completed_at' => '2026-10-02 21:10:00',
    ]);
    $pendingOffer = DogWorkOffer::factory()->create([
        'dog_work_board_id' => DogWorkOffer::query()->findOrFail($work->dog_work_offer_id)->dog_work_board_id,
    ]);
    $pendingWork = DogWorkShift::factory()->create([
        'user_id' => $owner->id, 'pet_id' => $pet->id,
        'dog_work_offer_id' => $pendingOffer->id,
    ]);
    $lesson = PetSkillLesson::factory()->create([
        'user_id' => $owner->id, 'pet_id' => $pet->id,
        'trained_at' => '2026-10-02 20:30:00',
    ]);
    $visit = VeterinaryVisit::factory()->create([
        'user_id' => $owner->id, 'pet_id' => $pet->id,
        'performed_at' => '2026-10-02 21:20:00',
    ]);

    $migration->up();

    $this->assertDatabaseHas('users', [
        'id' => $owner->id, 'experience' => '350', 'level' => 3,
        'walks_count' => 7, 'trainings_count' => 2, 'active_days' => 3,
        'last_pet_action_at' => '2026-10-02 21:20:00',
        'name' => $owner->name, 'username' => $owner->username,
        'email' => $owner->email, 'bio' => 'Люблю прогулки с собакой.',
    ]);
    expect($owner->fresh()->pet_statistics)->toEqual([
        'care.meal' => 2, 'care.water' => 1, 'care.walk' => 1,
        'care.home' => 1, 'training' => 1, 'work' => 1,
        'skill_training' => 1, 'veterinary.checkup' => 1,
    ]);
    $this->assertDatabaseHas('users', [
        'id' => $inactive->id, 'experience' => '700', 'level' => 4,
        'walks_count' => 0, 'trainings_count' => 0, 'active_days' => 0,
        'last_pet_action_at' => null,
    ]);
    expect($inactive->fresh()->pet_statistics)->toBe([]);
    foreach ($care as $receipt) {
        $this->assertDatabaseHas('pet_care_actions', ['id' => $receipt->id, 'experience_awarded' => 0]);
    }
    $this->assertDatabaseHas('dog_work_shifts', ['id' => $work->id, 'experience_awarded' => 0]);
    $this->assertDatabaseHas('pet_skill_lessons', ['id' => $lesson->id, 'experience_awarded' => 0]);
    $this->assertDatabaseHas('veterinary_visits', ['id' => $visit->id, 'experience_awarded' => 0]);
    $this->assertDatabaseHas('pet_care_actions', ['id' => $pendingCare->id, 'experience_awarded' => null]);
    $this->assertDatabaseHas('dog_work_shifts', ['id' => $pendingWork->id, 'experience_awarded' => null]);
});

test('progress migration keeps old 64-bit experience exactly and permits larger totals afterwards', function () {
    $migration = require database_path('migrations/2026_10_03_144445_add_player_progress_to_users_and_pet_receipts.php');
    $migration->down();
    $owner = User::factory()->create(['experience' => '9223372036854775807', 'level' => 1]);

    $migration->up();

    $this->assertDatabaseHas('users', [
        'id' => $owner->id, 'experience' => '9223372036854775807', 'level' => 57,
    ]);

    $owner->forceFill(['experience' => '1844674407370955161500'])->save();

    expect($owner->fresh()->experience)->toBe('1844674407370955161500');
});

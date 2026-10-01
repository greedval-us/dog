<?php

use App\Models\Pet;
use App\Models\PetSkillLesson;
use App\Models\Skill;
use App\Models\User;
use App\Modules\Pets\Actions\TrainPetSkill;
use App\Modules\Pets\Calculators\SkillRules;
use App\Modules\Pets\DTO\TrainPetSkillData;
use App\Modules\Pets\Enums\PetActivity;
use App\Modules\Pets\Queries\GetPetSkills;
use App\Modules\Players\Enums\PlayerStatus;
use Database\Seeders\SkillSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function skillLessonPayload(Skill $skill, int $level = 1): array
{
    return ['skill_id' => $skill->id, 'level' => $level, 'expected_price' => 100 * $level, 'token' => (string) Str::uuid()];
}

test('an instructor teaches one level at the exact requirements and charges coins once for retries', function () {
    $this->freezeSecond();
    $owner = User::factory()->create(['coins' => 100, 'gems' => 7]);
    $pet = Pet::factory()->for($owner)->create(['intelligence' => 10, 'obedience' => 5]);
    $skill = Skill::factory()->create();
    $payload = skillLessonPayload($skill);

    $this->actingAs($owner)->post(route('pets.skills.store', $pet), $payload)
        ->assertRedirect(route('dashboard', ['pet' => $pet->id]))->assertSessionHasNoErrors();
    $this->post(route('pets.skills.store', $pet), [...$payload, 'token' => strtoupper($payload['token'])])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('users', ['id' => $owner->id, 'coins' => 0, 'gems' => 7]);
    $this->assertDatabaseHas('pet_skill', ['pet_id' => $pet->id, 'skill_id' => $skill->id, 'level' => 1,
        'last_trained_at' => now()->toDateTimeString(), 'cooldown_until' => now()->addHours(24)->toDateTimeString()]);
    $this->assertDatabaseCount('pet_skill_lessons', 1);
    $this->assertDatabaseHas('currency_transactions', ['user_id' => $owner->id, 'currency' => 'coins', 'amount' => -100, 'reason' => 'skill_lesson']);
    $this->assertDatabaseCount('currency_transactions', 1);
});

test('all five levels cost coins in order and a mastered skill cannot be purchased again', function () {
    $this->freezeSecond();
    $owner = User::factory()->create(['coins' => 1500]);
    $pet = Pet::factory()->for($owner)->create(['intelligence' => 100, 'obedience' => 100]);
    $skill = Skill::factory()->create();
    $this->actingAs($owner);

    foreach (range(1, 5) as $level) {
        $this->post(route('pets.skills.store', $pet), skillLessonPayload($skill, $level))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pet_skill', ['pet_id' => $pet->id, 'skill_id' => $skill->id, 'level' => $level]);
        $this->travel(24)->hours();
    }
    $this->post(route('pets.skills.store', $pet), skillLessonPayload($skill, 5))
        ->assertSessionHasErrors(['skill' => __('Your dog has mastered all five levels of this skill.')]);

    $this->assertDatabaseHas('users', ['id' => $owner->id, 'coins' => 0]);
    $this->assertDatabaseCount('pet_skill_lessons', 5);
    $this->assertDatabaseCount('currency_transactions', 5);
    $view = app(GetPetSkills::class)->handle($owner->refresh(), $pet->id, 'en')['skills'][0];
    expect($view['level'])->toBe(5);
    expect($view['active'])->toBeTrue();
    expect($view['canTrain'])->toBeFalse();
});

test('the twenty four hour cooldown ends at its exact timestamp', function () {
    $this->freezeSecond();
    $owner = User::factory()->create(['coins' => 500]);
    $pet = Pet::factory()->for($owner)->create(['intelligence' => 100, 'obedience' => 100]);
    $skill = Skill::factory()->create();
    $this->actingAs($owner)->post(route('pets.skills.store', $pet), skillLessonPayload($skill))->assertSessionHasNoErrors();
    $next = skillLessonPayload($skill, 2);
    $this->travel(86399)->seconds();

    $this->post(route('pets.skills.store', $pet), $next)
        ->assertSessionHasErrors(['skill' => __('Wait 24 hours between lessons for the same skill.')]);
    $this->assertDatabaseHas('users', ['id' => $owner->id, 'coins' => 400]);
    $this->assertDatabaseCount('pet_skill_lessons', 1);
    $this->travel(1)->seconds();
    $this->post(route('pets.skills.store', $pet), $next)->assertSessionHasNoErrors();

    $this->assertDatabaseHas('users', ['id' => $owner->id, 'coins' => 200]);
    $this->assertDatabaseHas('pet_skill', ['pet_id' => $pet->id, 'skill_id' => $skill->id, 'level' => 2]);
});

test('cooldowns are independent for other skills and other dogs', function () {
    $this->freezeSecond();
    $owner = User::factory()->create(['coins' => 500]);
    $first = Pet::factory()->for($owner)->create(['intelligence' => 100, 'obedience' => 100]);
    $second = Pet::factory()->for($owner)->create(['intelligence' => 100, 'obedience' => 100]);
    $skills = Skill::factory()->count(2)->create();

    $this->actingAs($owner)->post(route('pets.skills.store', $first), skillLessonPayload($skills[0]))->assertSessionHasNoErrors();
    $this->post(route('pets.skills.store', $first), skillLessonPayload($skills[1]))->assertSessionHasNoErrors();
    $this->post(route('pets.skills.store', $second), skillLessonPayload($skills[0]))->assertSessionHasNoErrors();

    $this->assertDatabaseCount('pet_skill', 3);
    $this->assertDatabaseCount('pet_skill_lessons', 3);
    $this->assertDatabaseHas('users', ['id' => $owner->id, 'coins' => 200]);
});

test('each required current attribute must reach its percentage of the dogs genetic maximum', function (array $stats) {
    $this->freezeSecond();
    $owner = User::factory()->create(['coins' => 500]);
    $pet = Pet::factory()->for($owner)->create(['intelligence_potential' => 1000, 'obedience_potential' => 1000, ...$stats]);
    $skill = Skill::factory()->create();

    $this->actingAs($owner)->post(route('pets.skills.store', $pet), skillLessonPayload($skill))
        ->assertSessionHasErrors(['skill' => __('Raise your dog’s attributes to the lesson requirements.')]);

    $this->assertDatabaseHas('users', ['id' => $owner->id, 'coins' => 500]);
    $this->assertDatabaseCount('pet_skill', 0);
    $this->assertDatabaseCount('pet_skill_lessons', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with(['intelligence below ten percent' => [['intelligence' => 99, 'obedience' => 50]],
    'obedience below five percent' => [['intelligence' => 100, 'obedience' => 49]]]);

test('the same skill uses each dogs own potential and rounds fractional requirements upward', function (int $maximum, int $minimumIntelligence, int $minimumObedience) {
    $this->freezeSecond();
    $owner = User::factory()->create(['coins' => 500]);
    $pet = Pet::factory()->for($owner)->create([
        'intelligence_potential' => $maximum, 'obedience_potential' => $maximum,
        'intelligence' => $minimumIntelligence - 1, 'obedience' => $minimumObedience,
    ]);
    $skill = Skill::factory()->create();
    $query = app(GetPetSkills::class);
    $view = $query->handle($owner->refresh(), $pet->id, 'en')['skills'][0];
    expect($view['levels'][0]['requirements'])->toBe(['intelligence' => $minimumIntelligence, 'obedience' => $minimumObedience]);
    expect($view['levels'][0]['requirementPercentages'])->toBe(['intelligence' => 10, 'obedience' => 5]);
    expect($view['canTrain'])->toBeFalse();
    $payload = skillLessonPayload($skill);

    $this->actingAs($owner)->post(route('pets.skills.store', $pet), $payload)
        ->assertSessionHasErrors(['skill' => __('Raise your dog’s attributes to the lesson requirements.')]);

    $this->assertDatabaseHas('users', ['id' => $owner->id, 'coins' => 500]);
    $this->assertDatabaseCount('currency_transactions', 0);
    $pet->update(['intelligence' => $minimumIntelligence]);
    expect($query->handle($owner, $pet->id, 'en')['skills'][0]['canTrain'])->toBeTrue();

    $this->post(route('pets.skills.store', $pet), $payload)->assertSessionHasNoErrors();

    $this->assertDatabaseHas('pet_skill', ['pet_id' => $pet->id, 'skill_id' => $skill->id, 'level' => 1]);
    $this->assertDatabaseHas('users', ['id' => $owner->id, 'coins' => 400]);
    expect(PetSkillLesson::query()->where('pet_id', $pet->id)->firstOrFail()->requirements)
        ->toBe(['intelligence' => $minimumIntelligence, 'obedience' => $minimumObedience]);
})->with([
    'small potential' => [40, 4, 2],
    'large potential' => [200, 20, 10],
    'fractional threshold' => [83, 9, 5],
]);

test('a learned level is active only at the percentage threshold for this dogs maximum', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create([
        'intelligence_potential' => 83, 'obedience_potential' => 137,
        'intelligence' => 25, 'obedience' => 21,
    ]);
    $skill = Skill::factory()->create();
    $pet->skills()->attach($skill, ['level' => 3]);
    $query = app(GetPetSkills::class);
    $view = $query->handle($pet->user, $pet->id, 'en')['skills'][0];
    expect($view['activeRequirements'])->toBe(['intelligence' => 25, 'obedience' => 21]);
    expect($view['activeRequirementPercentages'])->toBe(['intelligence' => 30, 'obedience' => 15]);
    expect($view['active'])->toBeTrue();

    $pet->update(['obedience' => 20]);
    expect($query->handle($pet->user, $pet->id, 'en')['skills'][0]['active'])->toBeFalse();
    $pet->update(['obedience' => 21]);
    expect($query->handle($pet->user, $pet->id, 'en')['skills'][0]['active'])->toBeTrue();
    $this->assertDatabaseHas('pet_skill', ['pet_id' => $pet->id, 'skill_id' => $skill->id, 'level' => 3]);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('one hundred percent requires the dogs full individual potential', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create([
        'intelligence_potential' => 83, 'obedience_potential' => 137,
        'intelligence' => 82, 'obedience' => 35,
    ]);
    $skill = Skill::factory()->create(['levels' => array_map(fn (int $level): array => [
        'price' => 100 * $level, 'requirements' => ['intelligence' => 20 * $level, 'obedience' => 5 * $level],
    ], range(1, 5))]);
    $pet->skills()->attach($skill, ['level' => 4]);
    $payload = skillLessonPayload($skill, 5);
    $this->actingAs($pet->user)->post(route('pets.skills.store', $pet), $payload)
        ->assertSessionHasErrors(['skill' => __('Raise your dog’s attributes to the lesson requirements.')]);
    $pet->update(['intelligence' => 83]);

    $this->post(route('pets.skills.store', $pet), $payload)->assertSessionHasNoErrors();

    $view = app(GetPetSkills::class)->handle($pet->user->refresh(), $pet->id, 'en')['skills'][0];
    expect($view['active'])->toBeTrue();
    expect($view['activeRequirements'])->toBe(['intelligence' => 83, 'obedience' => 35]);
    $this->assertDatabaseHas('pet_skill', ['pet_id' => $pet->id, 'skill_id' => $skill->id, 'level' => 5]);
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'coins' => 0]);
    $this->assertDatabaseCount('currency_transactions', 1);
});

test('a required attribute with no genetic potential cannot qualify for a lesson or activate a learned skill', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create([
        'intelligence_potential' => 0, 'intelligence' => 0, 'obedience' => 100,
    ]);
    $skill = Skill::factory()->create();
    $query = app(GetPetSkills::class);
    expect($query->handle($pet->user, $pet->id, 'en')['skills'][0]['canTrain'])->toBeFalse();

    $this->actingAs($pet->user)->post(route('pets.skills.store', $pet), skillLessonPayload($skill))
        ->assertSessionHasErrors(['skill' => __('Raise your dog’s attributes to the lesson requirements.')]);

    $this->assertDatabaseCount('currency_transactions', 0);
    $this->assertDatabaseCount('pet_skill', 0);
    $pet->skills()->attach($skill, ['level' => 1]);
    expect($query->handle($pet->user, $pet->id, 'en')['skills'][0]['active'])->toBeFalse();
});

test('learning checks attribute decay that has not yet been written to the database', function () {
    $this->freezeSecond();
    $owner = User::factory()->create(['coins' => 500]);
    $pet = Pet::factory()->for($owner)->create(['intelligence' => 10, 'obedience' => 10]);
    $skill = Skill::factory()->create();
    $this->travel(40)->hours();

    $this->actingAs($owner)->post(route('pets.skills.store', $pet), skillLessonPayload($skill))
        ->assertSessionHasErrors(['skill' => __('Raise your dog’s attributes to the lesson requirements.')]);

    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'intelligence' => 10]);
    $this->assertDatabaseHas('users', ['id' => $owner->id, 'coins' => 500]);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('a learned skill becomes inactive after decay and reactivates at its current level requirements without another payment', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['intelligence' => 30, 'obedience' => 20]);
    $skill = Skill::factory()->create();
    $pet->skills()->attach($skill, ['level' => 3]);
    $query = app(GetPetSkills::class);
    expect($query->handle($pet->user, $pet->id, 'en')['skills'][0]['active'])->toBeTrue();
    $this->travel(40)->hours();

    $inactive = $query->handle($pet->user, $pet->id, 'en')['skills'][0];

    expect($inactive['active'])->toBeFalse();
    expect($inactive['level'])->toBe(3);
    expect($inactive['activeRequirements'])->toBe(['intelligence' => 30, 'obedience' => 15]);
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'intelligence' => 30]);
    $pet->update(['intelligence' => 30, 'obedience' => 15, 'stats_updated_at' => now()]);
    expect($query->handle($pet->user, $pet->id, 'en')['skills'][0]['active'])->toBeTrue();
    $this->assertDatabaseHas('pet_skill', ['pet_id' => $pet->id, 'skill_id' => $skill->id, 'level' => 3]);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('invalid unavailable out of order and unaffordable lessons do not spend coins', function (array $skillAttributes, array $payloadChanges, int $coins, string $message) {
    $this->freezeSecond();
    $owner = User::factory()->create(['coins' => $coins]);
    $pet = Pet::factory()->for($owner)->create(['intelligence' => 100, 'obedience' => 100]);
    $skill = Skill::factory()->create($skillAttributes);

    $this->actingAs($owner)->post(route('pets.skills.store', $pet), [...skillLessonPayload($skill), ...$payloadChanges])
        ->assertSessionHasErrors(['skill' => __($message)]);

    $this->assertDatabaseHas('users', ['id' => $owner->id, 'coins' => $coins]);
    $this->assertDatabaseCount('currency_transactions', 0);
    $this->assertDatabaseCount('pet_skill_lessons', 0);
    $this->assertDatabaseCount('pet_skill', 0);
})->with([
    'disabled' => [['is_active' => false], [], 500, 'This skill is unavailable.'],
    'no levels' => [['levels' => null], [], 500, 'This skill is unavailable.'],
    'invalid requirements' => [['levels' => array_fill(0, 5, ['price' => 100, 'requirements' => ['coins' => 1]])], [], 500, 'This skill is unavailable.'],
    'invalid price' => [['levels' => array_fill(0, 5, ['price' => 0, 'requirements' => ['intelligence' => 10]])], [], 500, 'This skill is unavailable.'],
    'percentage above maximum' => [['levels' => array_fill(0, 5, ['price' => 100, 'requirements' => ['intelligence' => 101]])], [], 500, 'This skill is unavailable.'],
    'zero percentage' => [['levels' => array_fill(0, 5, ['price' => 100, 'requirements' => ['intelligence' => 0]])], [], 500, 'This skill is unavailable.'],
    'missing skill' => [[], ['skill_id' => 999999], 500, 'This skill is unavailable.'],
    'skipped level' => [[], ['level' => 2, 'expected_price' => 200], 500, 'Learn skill levels in order. Refresh the page.'],
    'stale price' => [[], ['expected_price' => 99], 500, 'The lesson price has changed. Refresh the page.'],
    'insufficient coins' => [[], [], 99, 'You do not have enough coins to pay the instructor.'],
]);

test('retired and busy dogs cannot take a skill lesson', function (array $attributes, string $message) {
    $this->freezeSecond();
    $owner = User::factory()->create(['coins' => 500]);
    $pet = Pet::factory()->for($owner)->create(['intelligence' => 100, 'obedience' => 100, ...$attributes]);
    $skill = Skill::factory()->create();

    $this->actingAs($owner)->post(route('pets.skills.store', $pet), skillLessonPayload($skill))
        ->assertSessionHasErrors(['skill' => __($message)]);

    $this->assertDatabaseCount('pet_skill_lessons', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with([
    'retired' => [['retired_at' => '2026-01-01 00:00:00'], 'Retired dogs cannot learn skills.'],
    'busy' => [['activity' => PetActivity::Walk], 'Finish your dog’s current activity before a skill lesson.'],
]);

test('foreign and missing dogs return 404 without charging the current player', function (bool $missing) {
    $owner = User::factory()->create(['coins' => 500]);
    $pet = Pet::factory()->create();
    $skill = Skill::factory()->create();

    $this->actingAs($owner)->post(route('pets.skills.store', $missing ? 999999 : $pet->id), skillLessonPayload($skill))->assertNotFound();

    $this->assertDatabaseCount('currency_transactions', 0);
    $this->assertDatabaseCount('pet_skill', 0);
})->with(['foreign dog' => false, 'missing dog' => true]);

test('guests cannot purchase lessons', function () {
    $pet = Pet::factory()->create();
    $skill = Skill::factory()->create();

    $this->post(route('pets.skills.store', $pet), skillLessonPayload($skill))->assertRedirect(route('login'));

    $this->assertDatabaseCount('currency_transactions', 0);
});

test('blocked accounts are refused even when replaying an already paid lesson', function () {
    $owner = User::factory()->create(['coins' => 500]);
    $pet = Pet::factory()->for($owner)->create(['intelligence' => 100, 'obedience' => 100]);
    $skill = Skill::factory()->create();
    $payload = skillLessonPayload($skill);
    $this->actingAs($owner)->post(route('pets.skills.store', $pet), $payload)->assertSessionHasNoErrors();
    User::query()->whereKey($owner->id)->update(['status' => PlayerStatus::Blocked]);

    $this->post(route('pets.skills.store', $pet), $payload)->assertSessionHasErrors(['skill' => __('Your account is blocked.')]);

    $this->assertDatabaseCount('currency_transactions', 1);
    $this->assertDatabaseCount('pet_skill_lessons', 1);
});

test('reusing a token for another lesson rejects the changed parameters', function () {
    $owner = User::factory()->create(['coins' => 500]);
    $pet = Pet::factory()->for($owner)->create(['intelligence' => 100, 'obedience' => 100]);
    $skills = Skill::factory()->count(2)->create();
    $payload = skillLessonPayload($skills[0]);
    $this->actingAs($owner)->post(route('pets.skills.store', $pet), $payload)->assertSessionHasNoErrors();

    $this->post(route('pets.skills.store', $pet), [...$payload, 'skill_id' => $skills[1]->id])
        ->assertSessionHasErrors(['skill' => __('This token was already used for a different skill lesson.')]);

    $this->assertDatabaseCount('currency_transactions', 1);
    $this->assertDatabaseCount('pet_skill', 1);
});

test('a saved receipt is replayable after catalogue edits and deleting the dog', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create(['intelligence' => 100, 'obedience' => 100]);
    $owner = $pet->user;
    $skill = Skill::factory()->create();
    $data = new TrainPetSkillData($skill->id, 1, 100, (string) Str::uuid());
    $action = app(TrainPetSkill::class);
    $receipt = $action->handle($owner, $pet->id, $data);
    $skill->update(['levels' => null, 'is_active' => false]);
    $pet->delete();

    expect($action->handle($owner, $pet->id, $data)->id)->toBe($receipt->id);

    $this->assertDatabaseCount('currency_transactions', 1);
    $this->assertDatabaseHas('users', ['id' => $owner->id, 'coins' => 400]);
});

test('a failed receipt write rolls back the payment progress and cooldown and allows retry', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create(['intelligence' => 100, 'obedience' => 100]);
    $skill = Skill::factory()->create();
    $data = new TrainPetSkillData($skill->id, 1, 100, (string) Str::uuid());
    if (DB::getDriverName() === 'pgsql') {
        DB::unprepared("CREATE FUNCTION reject_skill_lesson() RETURNS trigger LANGUAGE plpgsql AS 'BEGIN RAISE EXCEPTION ''Simulated failure''; END'; CREATE TRIGGER reject_skill_lesson BEFORE INSERT ON pet_skill_lessons FOR EACH ROW EXECUTE FUNCTION reject_skill_lesson()");
    } else {
        DB::statement("CREATE TRIGGER reject_skill_lesson BEFORE INSERT ON pet_skill_lessons BEGIN SELECT RAISE(ABORT, 'Simulated failure'); END");
    }

    expect(fn () => app(TrainPetSkill::class)->handle($pet->user, $pet->id, $data))->toThrow(QueryException::class);

    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'coins' => 500]);
    $this->assertDatabaseCount('currency_transactions', 0);
    $this->assertDatabaseCount('pet_skill', 0);
    $this->assertDatabaseCount('pet_skill_lessons', 0);
    if (DB::getDriverName() === 'pgsql') {
        DB::statement('DROP TRIGGER reject_skill_lesson ON pet_skill_lessons');
        DB::statement('DROP FUNCTION reject_skill_lesson()');
    } else {
        DB::statement('DROP TRIGGER reject_skill_lesson');
    }
    app(TrainPetSkill::class)->handle($pet->user, $pet->id, $data);
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'coins' => 400]);
    $this->assertDatabaseCount('pet_skill', 1);
});

test('invalid request data never creates a skill lesson', function (array $changes, string $field) {
    $pet = Pet::factory()->create();
    $skill = Skill::factory()->create();

    $this->actingAs($pet->user)->post(route('pets.skills.store', $pet), [...skillLessonPayload($skill), ...$changes])
        ->assertSessionHasErrors($field);

    $this->assertDatabaseCount('currency_transactions', 0);
    $this->assertDatabaseCount('pet_skill', 0);
})->with(['invalid token' => [['token' => 'invalid'], 'token'], 'sixth level' => [['level' => 6], 'level'],
    'zero level' => [['level' => 0], 'level'], 'invalid skill' => [['skill_id' => 0], 'skill_id'],
    'invalid price' => [['expected_price' => 0], 'expected_price']]);

test('skill seeding adds six distinct five level skills and preserves edited balance', function () {
    $this->seed(SkillSeeder::class);
    $hunter = Skill::query()->where('code', 'hunter')->firstOrFail();
    $hunter->update(['name' => ['ru' => 'Охотник профи', 'en' => 'Expert hunter']]);
    $this->seed(SkillSeeder::class);

    $this->assertDatabaseCount('skills', 6);
    $this->assertDatabaseMissing('skills', ['code' => 'tracker']);
    expect($hunter->fresh()->name['ru'])->toBe('Охотник профи');
    foreach (Skill::query()->get() as $skill) {
        expect(app(SkillRules::class)->valid($skill->levels))->toBeTrue();
    }
    expect($hunter->levels[4])->toBe(['price' => 1320, 'requirements' => ['speed' => 100, 'endurance' => 80, 'intelligence' => 60]]);
});

test('the selected dogs skill data is deferred localized and isolated from other dogs', function () {
    $this->freezeSecond();
    $owner = User::factory()->create(['locale' => 'ru', 'coins' => 500]);
    $pet = Pet::factory()->for($owner)->create(['intelligence' => 10, 'obedience' => 5]);
    $other = Pet::factory()->create();
    $skill = Skill::factory()->create(['name' => ['ru' => 'Развитый нюх', 'en' => 'Keen nose']]);
    $other->skills()->attach($skill, ['level' => 5]);
    $this->actingAs($owner->refresh());

    $this->get(route('dashboard', ['pet' => $pet->id]))->assertInertia(fn (Assert $page) => $page
        ->missing('skills')->loadDeferredProps('skills', fn (Assert $deferred) => $deferred
        ->where('skills.skills.0.name', 'Развитый нюх')->where('skills.skills.0.level', 0)
        ->where('skills.skills.0.active', false)->where('skills.skills.0.canTrain', true)
        ->has('skills.token')->missing('care')->missing('appearance')));
    $this->get(route('dashboard', ['pet' => $other->id]))->assertNotFound();
    expect(app(GetPetSkills::class)->handle($owner, $pet->id, 'en')['skills'][0]['name'])->toBe('Keen nose');
});

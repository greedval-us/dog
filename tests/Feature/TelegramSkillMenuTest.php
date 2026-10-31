<?php

use App\Http\Telegram\Menus\MenuView;
use App\Http\Telegram\Menus\SkillMenu;
use App\Models\Pet;
use App\Models\Skill;
use App\Models\User;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Players\Enums\PlayerStatus;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

test('telegram skill lessons show localized escaped descriptions and the dogs individual requirements', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 1000, 'status' => PlayerStatus::Active]))
        ->create(['intelligence' => 50, 'intelligence_potential' => 101, 'obedience' => 50]);
    $skill = Skill::factory()->create([
        'name' => ['ru' => '<Сыщик>', 'en' => '<Tracker>'],
        'description' => ['ru' => '<Учимся искать>', 'en' => '<Learn to search>'],
    ]);

    $screen = app(SkillMenu::class)->lesson($pet->user, 'ru', ['petId' => $pet->id, 'skillId' => $skill->id]);

    expect($screen->text)->toContain('&lt;Сыщик&gt;', '&lt;Учимся искать&gt;', '11 (10%)')->not->toContain('<Сыщик>', 'Tracker');
    expect($screen->state['skillLesson'])->petId->toBe($pet->id)->skillId->toBe($skill->id)->level->toBe(1)->expectedPrice->toBe(100);
    expect(Str::isUuid($screen->state['skillLesson']['token']))->toBeTrue();
});

test('telegram skill pages keep skills beyond the first six accessible', function () {
    $pet = Pet::factory()->create();
    $skills = Skill::factory()->count(7)->create();
    $menu = app(SkillMenu::class);

    $first = $menu->index($pet->user, 'en', ['petId' => $pet->id]);
    $commands = collect($first->rows)->flatten(1)->pluck('command')->filter();
    $next = $commands->firstWhere('offset', 6);
    $second = $menu->index($pet->user, 'en', $next);
    $secondCommands = collect($second->rows)->flatten(1)->pluck('command')->filter();

    expect($commands->where('action', 'skills.lesson'))->toHaveCount(6);
    expect($secondCommands->firstWhere('action', 'skills.lesson')['skillId'])->toBe($skills->last()->id);
    expect($secondCommands->firstWhere('offset', 0)['action'])->toBe('skills');
});

test('telegram long skill names preserve the lesson price and confirmation within a bounded escaped screen', function () {
    $this->freezeSecond();
    $owner = User::factory()->create(['coins' => 1000, 'status' => PlayerStatus::Active]);
    $pet = Pet::factory()->for($owner)->create(['intelligence' => 50, 'obedience' => 50]);
    $skill = Skill::factory()->create(['name' => ['en' => str_repeat('<Focus> & навык ', 1000)]]);

    $screen = app(SkillMenu::class)->lesson($owner, 'en', ['petId' => $pet->id, 'skillId' => $skill->id]);

    expect($screen->text)->toContain('&lt;Focus&gt;', 'Price: 100 coins', 'for 100 coins?')->not->toContain('<Focus>');
    expect(mb_strlen($screen->text))->toBeLessThanOrEqual(4096);
    expect(mb_check_encoding($screen->text, 'UTF-8'))->toBeTrue();
    expect(mb_strlen($screen->state['skillLesson']['name']))->toBeLessThanOrEqual(123);
    expect(collect($screen->rows)->flatten(1)->pluck('command')->filter()->firstWhere('action', 'skills.train'))
        ->toBe(['action' => 'skills.train']);
});

test('telegram confirmed lessons charge once and show the learned level and cooldown on return', function () {
    $this->freezeSecond();
    $owner = User::factory()->create(['coins' => 500, 'status' => PlayerStatus::Active]);
    $pet = Pet::factory()->for($owner)->create(['intelligence' => 50, 'obedience' => 50]);
    $skill = Skill::factory()->create(['name' => ['en' => 'Tracker']]);
    $menu = app(SkillMenu::class);
    $confirmation = $menu->lesson($owner, 'en', ['petId' => $pet->id, 'skillId' => $skill->id]);

    $success = $menu->train($owner, 'en', $confirmation->state);
    $menu->train($owner, 'en', $confirmation->state);
    $cooldown = $menu->lesson($owner->fresh(), 'en', ['petId' => $pet->id, 'skillId' => $skill->id]);

    expect($success->text)->toContain('Tracker', '1');
    expect($owner->fresh()->coins)->toBe(400);
    expect(collect($cooldown->rows)->flatten(1)->pluck('command')->filter()->where('action', 'skills.train'))->toBeEmpty();
    expect($cooldown->text)->toContain(trans('telegram.skill.cooldown_until', ['time' => app(MenuView::class)->dateTime(now()->addSeconds(10)->toIso8601String(), $owner)], 'en'));
    $this->assertDatabaseCount('pet_skill_lessons', 1);
    $this->assertDatabaseCount('currency_transactions', 1);
    $this->assertDatabaseHas('pet_skill', ['pet_id' => $pet->id, 'skill_id' => $skill->id, 'level' => 1]);
});

test('telegram skill purchase refuses a changed price while preserving the confirmed quote', function () {
    $this->freezeSecond();
    $owner = User::factory()->create(['coins' => 1000, 'status' => PlayerStatus::Active]);
    $pet = Pet::factory()->for($owner)->create(['intelligence' => 50, 'obedience' => 50]);
    $skill = Skill::factory()->create();
    $menu = app(SkillMenu::class);
    $confirmation = $menu->lesson($owner, 'en', ['petId' => $pet->id, 'skillId' => $skill->id]);
    $levels = $skill->levels;
    $levels[0]['price'] = 200;
    $skill->update(['levels' => $levels]);

    expect(fn () => $menu->train($owner, 'en', $confirmation->state))
        ->toThrow(PetUnavailable::class, 'The lesson price has changed. Refresh the page.');

    expect($confirmation->state['skillLesson']['expectedPrice'])->toBe(100);
    expect($owner->fresh()->coins)->toBe(1000);
    $this->assertDatabaseCount('pet_skill_lessons', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('telegram skill screens refuse another players dog', function () {
    $owner = User::factory()->create();
    $foreign = Pet::factory()->create();
    $skill = Skill::factory()->create();
    $menu = app(SkillMenu::class);

    expect(fn () => $menu->index($owner, 'en', ['petId' => $foreign->id]))->toThrow(ModelNotFoundException::class);
    expect(fn () => $menu->lesson($owner, 'en', ['petId' => $foreign->id, 'skillId' => $skill->id]))->toThrow(ModelNotFoundException::class);
});

test('telegram unavailable lessons show a reason and omit the payment control', function (array $attributes, int $coins, string $reason) {
    $this->freezeSecond();
    $owner = User::factory()->create(['coins' => $coins, 'status' => PlayerStatus::Active]);
    $pet = Pet::factory()->for($owner)->create(['intelligence' => 50, 'obedience' => 50, ...$attributes]);
    $skill = Skill::factory()->create();

    $screen = app(SkillMenu::class)->lesson($owner, 'en', ['petId' => $pet->id, 'skillId' => $skill->id]);

    expect($screen->text)->toContain($reason);
    expect(collect($screen->rows)->flatten(1)->pluck('command')->filter()->where('action', 'skills.train'))->toBeEmpty();
    expect($screen->state)->not->toHaveKey('skillLesson');
    $this->assertDatabaseCount('pet_skill_lessons', 0);
})->with([
    'retired dog' => [['retired_at' => '2026-01-01 00:00:00'], 1000, 'Retired dogs cannot learn skills.'],
    'insufficient coins' => [[], 0, 'You do not have enough coins to pay the instructor.'],
    'insufficient attributes' => [['intelligence' => 0], 1000, 'Raise your dog’s attributes to the lesson requirements.'],
]);

test('telegram empty skill lists explain the empty state and return to the dog', function () {
    $pet = Pet::factory()->create();

    $screen = app(SkillMenu::class)->index($pet->user, 'en', ['petId' => $pet->id]);

    expect($screen->text)->toContain(trans('telegram.skill.empty', [], 'en'));
    expect(collect($screen->rows)->flatten(1)->pluck('command')->filter()->firstWhere('action', 'pet'))
        ->toBe(['action' => 'pet', 'petId' => $pet->id]);
});

test('telegram skill purchase with a missing confirmation becomes a stale screen', function () {
    $owner = User::factory()->create();

    expect(fn () => app(SkillMenu::class)->train($owner, 'en', []))->toThrow(PetUnavailable::class, 'telegram.errors.stale');

    $this->assertDatabaseCount('pet_skill_lessons', 0);
});

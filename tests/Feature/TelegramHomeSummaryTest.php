<?php

use App\Http\Telegram\Menus\HomeSummary;
use App\Models\DogWorkOffer;
use App\Models\DogWorkShift;
use App\Models\Pet;
use App\Models\PetCareAction;
use App\Models\User;
use App\Modules\Pets\Enums\PetActivity;
use Illuminate\Support\Facades\DB;

test('telegram home summarizes the current wallet and owned active dogs using their projected percentages', function () {
    $this->freezeSecond();
    $user = User::factory()->create(['coins' => 1, 'gems' => 0]);
    User::query()->whereKey($user->id)->update(['coins' => 410, 'gems' => 7]);
    $hungry = Pet::factory()->for($user)->create(['name' => '<Hungry>', 'satiety' => 79, 'satiety_max' => 200]);
    Pet::factory()->for($user)->create(['name' => 'At the boundary', 'satiety' => 100, 'satiety_max' => 200, 'bond' => 100, 'bond_max' => 100]);
    $retired = Pet::factory()->for($user)->retired()->create(['name' => 'Retired', 'hydration' => 40]);
    Pet::factory()->for($user)->deceased()->create();
    Pet::factory()->for($user)->create(['surrendered_at' => now(), 'satiety' => 1]);
    Pet::factory()->create(['satiety' => 1]);

    $screen = app(HomeSummary::class)->build($user, 'en');

    expect($screen->text)->toContain('410', '7',
        trans('telegram.home_summary.dogs', ['count' => 3], 'en'),
        trans('telegram.home_summary.needs_care', ['count' => 2], 'en'))
        ->not->toContain('telegram.');
    expect(collect($screen->rows)->flatten(1)->pluck('command')->all())->toBe([
        ['action' => 'pet', 'petId' => $hungry->id],
        ['action' => 'pet', 'petId' => $retired->id],
    ]);
    $this->assertDatabaseHas('pets', ['id' => $hungry->id, 'satiety' => 79]);
});

test('telegram home projects elapsed needs without persisting the pet snapshot', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['name' => 'Thirsty', 'hydration' => 55,
        'state_updated_at' => now()->subHours(2), 'stats_updated_at' => now()->subHours(2)]);

    $screen = app(HomeSummary::class)->build($pet->user, 'en');

    expect($screen->text)->toContain(trans('telegram.home_summary.needs_care', ['count' => 1], 'en'));
    expect($screen->rows[0][0]['command'])->toBe(['action' => 'pet', 'petId' => $pet->id]);
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'hydration' => 55, 'state_updated_at' => now()->subHours(2)]);
});

test('telegram home prioritizes ready care and work receipts before needy dogs without completing them', function () {
    $this->freezeSecond();
    $user = User::factory()->create(['coins' => 0]);
    $carePet = Pet::factory()->for($user)->create(['name' => '<Rex>']);
    $care = PetCareAction::factory()->create(['pet_id' => $carePet->id, 'ends_at' => now()->subSeconds(10)]);
    $carePet->update(['activity' => PetActivity::Play, 'activity_token' => $care->activity_token, 'activity_ends_at' => $care->ends_at]);
    $workPet = Pet::factory()->for($user)->create(['name' => 'Worker']);
    $shift = DogWorkShift::factory()->create(['pet_id' => $workPet->id, 'ends_at' => now()->subSeconds(20)]);
    $workPet->update(['activity' => PetActivity::Work, 'activity_token' => $shift->activity_token, 'activity_ends_at' => $shift->ends_at]);
    $needy = Pet::factory()->for($user)->count(4)->create(['satiety' => 10]);

    $screen = app(HomeSummary::class)->build($user, 'en');

    expect($screen->text)->toContain(trans('telegram.home_summary.activities', ['ready' => 2, 'pending' => 0], 'en'));
    expect(collect($screen->rows)->flatten(1)->pluck('command')->all())->toBe([
        ['action' => 'work.complete', 'token' => $shift->token],
        ['action' => 'care.complete', 'petId' => $carePet->id, 'token' => $care->token],
        ['action' => 'pet', 'petId' => $needy->first()->id],
    ]);
    $this->assertDatabaseHas('pet_care_actions', ['id' => $care->id, 'completed_at' => null]);
    $this->assertDatabaseHas('dog_work_shifts', ['id' => $shift->id, 'completed_at' => null]);
    $this->assertDatabaseHas('users', ['id' => $user->id, 'coins' => 0]);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('telegram home reports pending activities in the player timezone and ignores stale foreign or finished receipts', function () {
    $this->travelTo('2026-11-02 10:00:00 UTC');
    $pet = Pet::factory()->create(['name' => '<Pending>']);
    $pet->user->forceFill(['timezone' => 'Europe/Moscow'])->save();
    $care = PetCareAction::factory()->create(['pet_id' => $pet->id, 'ends_at' => now()->addMinutes(2)]);
    $pet->update(['activity' => PetActivity::Play, 'activity_token' => $care->activity_token, 'activity_ends_at' => $care->ends_at]);
    PetCareAction::factory()->create(['pet_id' => $pet->id, 'ends_at' => now()->subMinute()]);
    PetCareAction::factory()->create(['ends_at' => now()->subMinute()]);
    $completed = DogWorkShift::factory()->create(['pet_id' => $pet->id, 'completed_at' => now()]);
    $anotherOffer = DogWorkOffer::factory()->create(['dog_work_board_id' => DogWorkOffer::query()->findOrFail($completed->dog_work_offer_id)->dog_work_board_id]);
    DogWorkShift::factory()->create(['pet_id' => $pet->id, 'dog_work_offer_id' => $anotherOffer->id, 'cancelled_at' => now()]);

    $screen = app(HomeSummary::class)->build($pet->user, 'ru');

    expect($screen->text)->toContain(trans('telegram.home_summary.activities', ['ready' => 0, 'pending' => 1], 'ru'), '02.11 13:02')
        ->not->toContain('telegram.');
    expect($screen->rows)->toHaveCount(1);
    expect($screen->rows[0][0]['command'])->toBe(['action' => 'pet', 'petId' => $pet->id]);
});

test('telegram home keeps receipt reads bounded and does not query per-dog catalogues', function () {
    $this->freezeSecond();
    $user = User::factory()->create();
    $pets = Pet::factory()->for($user)->count(6)->create(['satiety' => 10]);
    foreach ($pets as $pet) {
        $care = PetCareAction::factory()->create(['pet_id' => $pet->id, 'ends_at' => now()->subMinute()]);
        $pet->update(['activity' => PetActivity::Play, 'activity_token' => $care->activity_token]);
    }
    DB::enableQueryLog();
    DB::flushQueryLog();

    $screen = app(HomeSummary::class)->build($user, 'en');
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($screen->text)->toContain(trans('telegram.home_summary.activities', ['ready' => 6, 'pending' => 0], 'en'));
    expect($screen->rows)->toHaveCount(3);
    expect(count($queries))->toBeLessThanOrEqual(6);
    expect(implode(' ', array_column($queries, 'query')))->not->toContain('"trainings"', '"game_assets"', 'insert ', 'update ');
});

test('telegram home provides a localized empty summary for a player without dogs or activities', function () {
    $this->freezeSecond();
    $user = User::factory()->create();

    $screen = app(HomeSummary::class)->build($user, 'ru');

    expect($screen->text)->toContain(trans('telegram.home_summary.dogs', ['count' => 0], 'ru'),
        trans('telegram.home_summary.no_activities', [], 'ru'))->not->toContain('telegram.');
    expect($screen->rows)->toBeEmpty();
});

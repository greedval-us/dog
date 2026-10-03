<?php

use App\Models\CurrencyTransaction;
use App\Models\DogWorkBoard;
use App\Models\DogWorkOffer;
use App\Models\DogWorkShift;
use App\Models\DogWorkType;
use App\Models\Pet;
use App\Models\Skill;
use App\Models\User;
use App\Modules\Pets\Actions\CompleteDogWork;
use App\Modules\Pets\Actions\GenerateDogWorkBoard;
use App\Modules\Pets\Actions\StartDogWork;
use App\Modules\Pets\DTO\StartDogWorkData;
use App\Modules\Pets\Enums\PetActivity;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Queries\GetDogWorkBoard;
use App\Modules\Players\Enums\PlayerStatus;
use Carbon\CarbonImmutable;
use Database\Seeders\DogWorkTypeSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/** @param array<string, mixed> $attributes */
function dogWorkReadyPet(DogWorkOffer $offer, ?User $user = null, array $attributes = []): Pet
{
    $pet = Pet::factory()->for($user ?? User::factory()->create(['coins' => 0, 'gems' => 0]))
        ->create(['intelligence' => 100, 'obedience' => 100, 'energy' => 100, ...$attributes]);
    $pet->skills()->attach($offer->required_skill_id, ['level' => $offer->required_skill_level]);

    return $pet;
}

/** @return array{pet_id: int, offer_id: int, token: string} */
function dogWorkPayload(Pet $pet, DogWorkOffer $offer): array
{
    return ['pet_id' => $pet->id, 'offer_id' => $offer->id, 'token' => (string) Str::uuid()];
}

test('starting reserves one place and energy and exact-time completion rewards once', function () {
    $this->freezeSecond();
    $offer = DogWorkOffer::factory()->create(['gems_reward' => 2]);
    $pet = dogWorkReadyPet($offer);
    $payload = dogWorkPayload($pet, $offer);
    $this->actingAs($pet->user)->post(route('dog-work.store'), [...$payload, 'coins_reward' => 999999])
        ->assertRedirect(route('dog-work.index', ['pet' => $pet->id]))->assertSessionHasNoErrors();
    $this->post(route('dog-work.store'), [...$payload, 'token' => strtoupper($payload['token'])])->assertSessionHasNoErrors();
    $shift = DogWorkShift::query()->sole();

    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'energy' => 90, 'activity' => 'work', 'activity_token' => $shift->activity_token]);
    $this->assertDatabaseHas('dog_work_offers', ['id' => $offer->id, 'reserved_count' => 1]);
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'coins' => 0, 'gems' => 0]);
    $this->assertDatabaseCount('currency_transactions', 0);
    $this->travelTo($shift->ends_at->subSecond());
    $this->post(route('dog-work.complete'), ['token' => $shift->token])->assertSessionHasErrors('work');
    $this->assertDatabaseCount('currency_transactions', 0);
    expect($pet->refresh()->isBusy())->toBeTrue();

    $this->travelTo($shift->ends_at);
    $this->post(route('dog-work.complete'), ['token' => $shift->token])->assertSessionHasNoErrors();
    $this->post(route('dog-work.complete'), ['token' => strtoupper($shift->token)])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'coins' => 150, 'gems' => 2]);
    $this->assertDatabaseCount('dog_work_shifts', 1);
    $this->assertDatabaseCount('currency_transactions', 2);
    $this->assertDatabaseHas('dog_work_shifts', ['id' => $shift->id, 'completed_at' => now()->toDateTimeString()]);
    expect($pet->refresh()->isBusy())->toBeFalse();
});

test('the last daily place is shared by different players', function () {
    $this->freezeSecond();
    $offer = DogWorkOffer::factory()->create(['daily_limit' => 1]);
    $first = dogWorkReadyPet($offer);
    $second = dogWorkReadyPet($offer);
    $this->actingAs($first->user)->post(route('dog-work.store'), dogWorkPayload($first, $offer))->assertSessionHasNoErrors();
    $this->actingAs($second->user)->post(route('dog-work.store'), dogWorkPayload($second, $offer))
        ->assertSessionHasErrors(['work' => __('All places for this job have been taken.')]);
    $this->assertDatabaseCount('dog_work_shifts', 1);
    $this->assertDatabaseHas('dog_work_offers', ['id' => $offer->id, 'reserved_count' => 1]);
    $this->assertDatabaseHas('pets', ['id' => $second->id, 'energy' => 100, 'activity' => null]);

    $shift = DogWorkShift::query()->sole();
    $this->travelTo($shift->ends_at);
    app(CompleteDogWork::class)->handle($first->user, $shift->token);
    $this->post(route('dog-work.store'), dogWorkPayload($second, $offer))->assertSessionHasErrors('work');
    $this->assertDatabaseHas('dog_work_offers', ['id' => $offer->id, 'reserved_count' => 1]);
});

test('a player cannot reserve the same daily job with a second dog even after completion', function () {
    $this->freezeSecond();
    $offer = DogWorkOffer::factory()->create();
    $first = dogWorkReadyPet($offer);
    $second = dogWorkReadyPet($offer, $first->user);
    $this->actingAs($first->user)->post(route('dog-work.store'), dogWorkPayload($first, $offer))->assertSessionHasNoErrors();
    $shift = DogWorkShift::query()->sole();
    $this->post(route('dog-work.store'), dogWorkPayload($second, $offer))->assertSessionHasErrors('work');
    $this->travelTo($shift->ends_at);
    app(CompleteDogWork::class)->handle($first->user, $shift->token);
    $this->post(route('dog-work.store'), dogWorkPayload($second, $offer))->assertSessionHasErrors('work');
    $this->assertDatabaseCount('dog_work_shifts', 1);
});

test('daily selection is global stable and takes newly inserted catalogue jobs on the next day', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-01 12:00:00', 'Europe/Moscow'));
    config(['doglive.dog_work_daily_offers' => 3]);
    DogWorkType::factory()->count(7)->create();
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('dog-work.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('DogWork')->has('board.offers', 3)->has('board.dogs', 0));
    $board = DogWorkBoard::query()->sole();
    $ids = $board->offers()->pluck('dog_work_type_id')->all();
    $this->actingAs(User::factory()->create())->get(route('dog-work.index'))->assertOk();
    expect($board->offers()->pluck('dog_work_type_id')->all())->toBe($ids);
    $this->assertDatabaseCount('dog_work_boards', 1);

    DogWorkType::query()->update(['is_active' => false]);
    $new = DogWorkType::factory()->create(['name' => ['ru' => 'Новая работа', 'en' => 'New catalogue job'], 'coins_reward' => 321]);
    app(GenerateDogWorkBoard::class)->handle();
    expect($board->offers()->pluck('dog_work_type_id')->all())->toBe($ids);
    $this->travel(1)->day();
    $tomorrow = app(GenerateDogWorkBoard::class)->handle();
    expect($tomorrow->offers()->sole()->dog_work_type_id)->toBe($new->id);
    expect($tomorrow->offers()->sole()->coins_reward)->toBe(321);
    $this->assertDatabaseCount('dog_work_boards', 2);
});

test('an empty daily selection stays empty until the next game day', function () {
    $this->freezeSecond();
    $board = app(GenerateDogWorkBoard::class)->handle();
    DogWorkType::factory()->create();
    expect(app(GenerateDogWorkBoard::class)->handle()->id)->toBe($board->id);
    expect($board->offers()->count())->toBe(0);
    $this->travel(1)->day();
    expect(app(GenerateDogWorkBoard::class)->handle()->offers()->count())->toBe(1);
});

test('catalogue edits do not change today’s terms or an accepted reward', function () {
    $this->freezeSecond();
    $job = DogWorkType::factory()->create();
    $board = app(GenerateDogWorkBoard::class)->handle();
    $offer = $board->offers()->sole();
    $pet = dogWorkReadyPet($offer);
    $job->update(['coins_reward' => 900, 'duration_seconds' => 1, 'daily_limit' => 1, 'is_active' => false]);
    $shift = app(StartDogWork::class)->handle($pet->user, $pet->id, new StartDogWorkData($offer->id, (string) Str::uuid()));
    expect($shift->coins_reward)->toBe(150);
    expect($shift->ends_at->diffInSeconds($shift->started_at, true))->toBe(1800.0);
    expect($offer->refresh()->daily_limit)->toBe(20);
    $offer->update(['coins_reward' => 999]);
    $job->delete();
    $this->travelTo($shift->ends_at);
    app(CompleteDogWork::class)->handle($pet->user, $shift->token);
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'coins' => 150]);
});

test('game-day rollover uses Moscow time and yesterday’s work can still finish', function () {
    config(['app.timezone' => 'America/Los_Angeles']);
    $this->travelTo(CarbonImmutable::parse('2026-10-01 23:59:59', 'Europe/Moscow'));
    $job = DogWorkType::factory()->create();
    $board = app(GenerateDogWorkBoard::class)->handle();
    $offer = $board->offers()->sole();
    $pet = dogWorkReadyPet($offer);
    $payload = dogWorkPayload($pet, $offer);
    $shift = app(StartDogWork::class)->handle($pet->user, $pet->id, new StartDogWorkData($offer->id, $payload['token']));
    $other = dogWorkReadyPet($offer);
    $this->travel(1)->second();
    $nextBoard = app(GenerateDogWorkBoard::class)->handle();
    expect($nextBoard->work_date->toDateString())->toBe('2026-10-02');
    expect($nextBoard->offers()->sole()->reserved_count)->toBe(0);
    $this->actingAs($other->user)->post(route('dog-work.store'), dogWorkPayload($other, $offer))->assertSessionHasErrors('work');
    expect(app(StartDogWork::class)->handle($pet->user, $pet->id, new StartDogWorkData($offer->id, $payload['token']))->id)->toBe($shift->id);
    $view = app(GetDogWorkBoard::class)->handle($pet->user, $nextBoard, $pet->id, 'en');
    expect($view['shifts'][0]['token'])->toBe($shift->token);
    expect(CarbonImmutable::parse($view['resetsAt'])->timezone('Europe/Moscow')->format('Y-m-d H:i'))->toBe('2026-10-03 00:00');
    $this->travelTo($shift->ends_at);
    app(CompleteDogWork::class)->handle($pet->user, $shift->token);
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'coins' => 150]);
});

test('job requirements use individual potential and the learned level must be active', function () {
    $this->freezeSecond();
    $offer = DogWorkOffer::factory()->create();
    $pet = dogWorkReadyPet($offer, attributes: ['intelligence' => 59, 'intelligence_potential' => 199, 'obedience' => 30]);
    $pet->skills()->updateExistingPivot($offer->required_skill_id, ['level' => 3]);
    $payload = dogWorkPayload($pet, $offer);
    $this->actingAs($pet->user)->post(route('dog-work.store'), $payload)
        ->assertSessionHasErrors(['work' => __('Raise your dog’s attributes to reactivate this skill.')]);
    $this->assertDatabaseCount('dog_work_shifts', 0);
    $pet->update(['intelligence' => 60]);
    $this->post(route('dog-work.store'), $payload)->assertSessionHasNoErrors();
    $this->assertDatabaseCount('dog_work_shifts', 1);
});

test('missing low disabled or malformed skills cannot qualify for work', function (string $condition) {
    $this->freezeSecond();
    $offer = DogWorkOffer::factory()->create(['required_skill_level' => 2]);
    $pet = dogWorkReadyPet($offer);
    match ($condition) {
        'missing' => $pet->skills()->detach(),
        'low level' => $pet->skills()->updateExistingPivot($offer->required_skill_id, ['level' => 1]),
        'invalid learned level' => $pet->skills()->updateExistingPivot($offer->required_skill_id, ['level' => 6]),
        'disabled' => Skill::query()->findOrFail($offer->required_skill_id)->update(['is_active' => false]),
        'malformed' => Skill::query()->findOrFail($offer->required_skill_id)->update(['levels' => []]),
    };
    $this->actingAs($pet->user)->post(route('dog-work.store'), dogWorkPayload($pet, $offer))->assertSessionHasErrors('work');
    $this->assertDatabaseCount('dog_work_shifts', 0);
    $this->assertDatabaseHas('dog_work_offers', ['id' => $offer->id, 'reserved_count' => 0]);
})->with(['missing', 'low level', 'invalid learned level', 'disabled', 'malformed']);

test('busy retired tired and blocked participants cannot reserve a place', function (string $condition) {
    $this->freezeSecond();
    $offer = DogWorkOffer::factory()->create();
    $pet = dogWorkReadyPet($offer);
    match ($condition) {
        'busy' => $pet->update(['activity' => PetActivity::Walk, 'activity_ends_at' => now()->subSecond()]),
        'retired' => $pet->update(['retired_at' => now()]),
        'tired' => $pet->update(['energy' => 9]),
        'blocked' => $pet->user->forceFill(['status' => PlayerStatus::Blocked])->save(),
    };
    $response = $this->actingAs($pet->user)->post(route('dog-work.store'), dogWorkPayload($pet, $offer));
    if ($condition === 'blocked') {
        $response->assertForbidden();
    } else {
        $response->assertSessionHasErrors('work');
    }
    $this->assertDatabaseCount('dog_work_shifts', 0);
    $this->assertDatabaseHas('dog_work_offers', ['id' => $offer->id, 'reserved_count' => 0]);
})->with(['busy', 'retired', 'tired', 'blocked']);

test('natural skill decay during work does not prevent completion', function () {
    $this->freezeSecond();
    $offer = DogWorkOffer::factory()->create();
    $pet = dogWorkReadyPet($offer);
    $shift = app(StartDogWork::class)->handle($pet->user, $pet->id, new StartDogWorkData($offer->id, (string) Str::uuid()));
    $pet->refresh()->update(['intelligence' => 0, 'obedience' => 0]);
    Skill::query()->findOrFail($offer->required_skill_id)->update(['is_active' => false]);
    $this->travelTo($shift->ends_at);
    $this->actingAs($pet->user)->post(route('dog-work.complete'), ['token' => $shift->token])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'coins' => 150]);
    expect($pet->refresh()->isBusy())->toBeFalse();
});

test('a work dog cannot start care or a skill lesson even after its timer elapsed', function () {
    $this->freezeSecond();
    $offer = DogWorkOffer::factory()->create();
    $pet = dogWorkReadyPet($offer);
    $shift = app(StartDogWork::class)->handle($pet->user, $pet->id, new StartDogWorkData($offer->id, (string) Str::uuid()));
    $this->travelTo($shift->ends_at);
    $this->actingAs($pet->user)->post(route('pets.care.store', $pet), ['variant' => 'attention', 'inventory_item_ids' => [], 'token' => (string) Str::uuid()])
        ->assertSessionHasErrors();
    $this->post(route('pets.skills.store', $pet), ['skill_id' => $offer->required_skill_id, 'level' => 2, 'expected_price' => 200, 'token' => (string) Str::uuid()])
        ->assertSessionHasErrors('skill');
    $this->get(route('dashboard', ['pet' => $pet->id]))->assertInertia(fn (Assert $page) => $page
        ->loadDeferredProps('care', fn (Assert $reload) => $reload->where('care.working', true)->where('care.busy', true)));
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'activity_token' => $shift->activity_token]);
});

test('a failed shift record rolls back both the reservation and dog activity', function () {
    $this->freezeSecond();
    $offer = DogWorkOffer::factory()->create();
    $pet = dogWorkReadyPet($offer);
    DogWorkShift::creating(fn () => throw new RuntimeException('Cannot write shift.'));
    try {
        expect(fn () => app(StartDogWork::class)->handle($pet->user, $pet->id, new StartDogWorkData($offer->id, (string) Str::uuid())))
            ->toThrow(RuntimeException::class, 'Cannot write shift.');
    } finally {
        DogWorkShift::flushEventListeners();
    }
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'energy' => 100, 'activity' => null]);
    $this->assertDatabaseHas('dog_work_offers', ['id' => $offer->id, 'reserved_count' => 0]);
    $this->assertDatabaseCount('dog_work_shifts', 0);
});

test('failed bonus payout rolls back coins completion and freeing the dog', function () {
    $this->freezeSecond();
    $offer = DogWorkOffer::factory()->create(['gems_reward' => 1]);
    $pet = dogWorkReadyPet($offer);
    $shift = app(StartDogWork::class)->handle($pet->user, $pet->id, new StartDogWorkData($offer->id, (string) Str::uuid()));
    $this->travelTo($shift->ends_at);
    CurrencyTransaction::creating(function (CurrencyTransaction $entry): void {
        if ($entry->currency === 'gems') {
            throw new RuntimeException('Cannot write bonus.');
        }
    });
    try {
        expect(fn () => app(CompleteDogWork::class)->handle($pet->user, $shift->token))->toThrow(RuntimeException::class, 'Cannot write bonus.');
    } finally {
        CurrencyTransaction::flushEventListeners();
    }
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'coins' => 0, 'gems' => 0]);
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'activity_token' => $shift->activity_token]);
    $this->assertDatabaseHas('dog_work_shifts', ['id' => $shift->id, 'completed_at' => null]);
    $this->assertDatabaseCount('currency_transactions', 0);
    app(CompleteDogWork::class)->handle($pet->user, $shift->token);
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'coins' => 150, 'gems' => 1]);
});

test('completion cannot free an unrelated activity or grant an orphaned reward', function (string $condition) {
    $this->freezeSecond();
    $offer = DogWorkOffer::factory()->create();
    $pet = dogWorkReadyPet($offer);
    $shift = app(StartDogWork::class)->handle($pet->user, $pet->id, new StartDogWorkData($offer->id, (string) Str::uuid()));
    $this->travelTo($shift->ends_at);
    if ($condition === 'activity changed') {
        $pet->refresh()->update(['activity_token' => (string) Str::uuid(), 'activity' => PetActivity::Walk]);
    } else {
        CurrencyTransaction::factory()->for($pet->user)->create(['operation_key' => 'dog-work:'.$shift->id.':coins', 'currency' => 'coins', 'amount' => 150, 'balance_after' => 150, 'reason' => 'dog_work']);
    }
    $this->actingAs($pet->user)->post(route('dog-work.complete'), ['token' => $shift->token])->assertSessionHasErrors('work');
    expect($pet->refresh()->isBusy())->toBeTrue();
    $this->assertDatabaseHas('dog_work_shifts', ['id' => $shift->id, 'completed_at' => null]);
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'coins' => 0]);
})->with(['activity changed', 'orphan reward']);

test('a start token cannot be reused for another dog or job', function (string $change) {
    $this->freezeSecond();
    $offer = DogWorkOffer::factory()->create();
    $pet = dogWorkReadyPet($offer);
    $payload = dogWorkPayload($pet, $offer);
    $this->actingAs($pet->user)->post(route('dog-work.store'), $payload)->assertSessionHasNoErrors();
    $payload[$change] = $change === 'pet_id' ? dogWorkReadyPet($offer, $pet->user)->id : 999999;
    $this->post(route('dog-work.store'), $payload)->assertSessionHasErrors('work');
    $this->assertDatabaseCount('dog_work_shifts', 1);
})->with(['pet_id', 'offer_id']);

test('another player cannot choose start or complete work for someone else’s dog', function () {
    $this->freezeSecond();
    $offer = DogWorkOffer::factory()->create();
    $pet = dogWorkReadyPet($offer);
    $shift = app(StartDogWork::class)->handle($pet->user, $pet->id, new StartDogWorkData($offer->id, (string) Str::uuid()));
    $this->actingAs(User::factory()->create())->get(route('dog-work.index', ['pet' => $pet->id]))->assertNotFound();
    $this->post(route('dog-work.store'), dogWorkPayload($pet, $offer))->assertNotFound();
    $this->travelTo($shift->ends_at);
    $this->post(route('dog-work.complete'), ['token' => $shift->token])->assertNotFound();
    $this->get(route('dog-work.index'))->assertInertia(fn (Assert $page) => $page->has('board.shifts', 0)->has('board.dogs', 0));
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('blocked players cannot replay starts or collect rewards', function () {
    $this->freezeSecond();
    $offer = DogWorkOffer::factory()->create();
    $pet = dogWorkReadyPet($offer);
    $payload = dogWorkPayload($pet, $offer);
    $shift = app(StartDogWork::class)->handle($pet->user, $pet->id, new StartDogWorkData($offer->id, $payload['token']));
    $staleOwner = $pet->user->fresh();
    $pet->user->forceFill(['status' => PlayerStatus::Blocked])->save();
    $this->travelTo($shift->ends_at);
    $this->actingAs($pet->user)->post(route('dog-work.store'), $payload)->assertForbidden();
    $this->post(route('dog-work.complete'), ['token' => $shift->token])->assertForbidden();
    expect(fn () => app(StartDogWork::class)->handle($staleOwner, $pet->id, new StartDogWorkData($offer->id, $payload['token'])))
        ->toThrow(PetUnavailable::class, 'Your account is blocked.');
    expect(fn () => app(CompleteDogWork::class)->handle($staleOwner, $shift->token))
        ->toThrow(PetUnavailable::class, 'Your account is blocked.');
    $this->assertDatabaseCount('currency_transactions', 0);
    expect($pet->refresh()->isBusy())->toBeTrue();
});

test('work routes require authentication and validate start and completion input', function () {
    $this->get(route('dog-work.index'))->assertRedirect(route('login'));
    $this->post(route('dog-work.store'), [])->assertRedirect(route('login'));
    $this->post(route('dog-work.complete'), [])->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->post(route('dog-work.store'), ['pet_id' => -1, 'offer_id' => 'code', 'token' => 'bad'])
        ->assertSessionHasErrors(['pet_id', 'offer_id', 'token']);
    $this->post(route('dog-work.complete'), ['token' => 'bad'])->assertSessionHasErrors('token');
    $this->post(route('dog-work.store'), ['pet_id' => 1, 'offer_id' => 99999, 'token' => (string) Str::uuid()])->assertNotFound();
    $this->assertDatabaseCount('dog_work_shifts', 0);
});

test('invalid or disabled catalogue records are excluded from new selections', function (string $condition) {
    $this->freezeSecond();
    $job = DogWorkType::factory()->create();
    match ($condition) {
        'disabled job' => $job->update(['is_active' => false]),
        'disabled skill' => $job->skill->update(['is_active' => false]),
        'malformed skill' => $job->skill->update(['levels' => []]),
        'zero reward' => $job->update(['coins_reward' => 0]),
        'zero duration' => $job->update(['duration_seconds' => 0]),
        'zero limit' => $job->update(['daily_limit' => 0]),
        'invalid level' => $job->update(['required_skill_level' => 6]),
    };
    expect(app(GenerateDogWorkBoard::class)->handle()->offers()->count())->toBe(0);
})->with(['disabled job', 'disabled skill', 'malformed skill', 'zero reward', 'zero duration', 'zero limit', 'invalid level']);

test('the work board is localized and reflects skill activity and remaining places without saving decay', function () {
    $this->freezeSecond();
    $offer = DogWorkOffer::factory()->create(['reserved_count' => 7]);
    $pet = dogWorkReadyPet($offer, attributes: ['intelligence' => 1]);
    $this->actingAs($pet->user)->withSession(['locale' => 'ru'])->get(route('dog-work.index', ['pet' => $pet->id]))->assertInertia(fn (Assert $page) => $page
        ->component('DogWork')->where('board.offers.0.name', 'Поиск в парке')->where('board.offers.0.places', 13)
        ->where('board.offers.0.skillActive', false)->where('board.selectedPetId', $pet->id));
    $updatedAt = $pet->stats_updated_at;
    $this->travel(1)->hour();
    app(GetDogWorkBoard::class)->handle($pet->user, $offer->board, $pet->id, 'en');
    expect($pet->refresh()->stats_updated_at->equalTo($updatedAt))->toBeTrue();
});

test('seeded jobs cover all skills and rerunning the seeder preserves edited terms', function () {
    $this->seed(DogWorkTypeSeeder::class);
    expect(DogWorkType::query()->count())->toBe(18);
    expect(DogWorkType::query()->distinct()->count('required_skill_id'))->toBe(6);
    $job = DogWorkType::query()->where('code', 'search_volunteer')->sole();
    expect($job->daily_limit)->toBe(20);
    $job->update(['coins_reward' => 999, 'daily_limit' => 50, 'is_active' => false]);
    $this->seed(DogWorkTypeSeeder::class);
    expect($job->refresh()->coins_reward)->toBe(999);
    expect($job->daily_limit)->toBe(50);
    expect($job->is_active)->toBeFalse();
});

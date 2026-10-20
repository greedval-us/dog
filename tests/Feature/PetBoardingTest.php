<?php

use App\Models\CurrencyTransaction;
use App\Models\GameEvent;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Actions\RegisterGameEvent;
use App\Modules\Pets\Actions\UpdatePetBoarding;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\Enums\PetBoardingPlan;
use App\Modules\Pets\Services\GameEventProcessor;
use App\Modules\Pets\Services\PetLifecycle;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00:00', 'UTC'));
});

test('boarding is disabled by default and its prices are private profile settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('profile.edit'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('boarding', [
            'plan' => 'off', 'inactivitySeconds' => 3600, 'hourlyPrices' => ['cheap' => 1, 'premium' => 3],
        ]));

    $this->get(route('players.show', $user->username))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->missing('boarding')->missing('player.boarding'));
});

test('boarding starts after one full idle hour and does not charge twice for its paid hour', function () {
    $user = User::factory()->create(['coins' => 10, 'boarding_plan' => 'cheap', 'boarding_enabled_at' => now(), 'last_seen_at' => now()]);
    $pet = Pet::factory()->create(['user_id' => $user->id, 'satiety' => 80, 'hydration' => 70]);
    $this->travel(3599)->seconds();
    app(PetLifecycle::class)->synchronizeOwner($user);
    expect($user->fresh()->coins)->toBe(10);

    $this->travel(1)->seconds();
    app(PetLifecycle::class)->synchronizeOwner($user);
    app(PetLifecycle::class)->synchronizeOwner($user);

    expect($user->fresh()->coins)->toBe(9);
    expect($pet->fresh()->satiety)->toBe((float) $pet->satiety_max);
    expect($pet->fresh()->hydration)->toBe((float) $pet->hydration_max);
    expect(CurrencyTransaction::query()->where('reason', 'pet_boarding')->count())->toBe(1);
});

test('boarding applies its own hourly losses and keeps food and water filled', function (string $plan, int $spent, float $bond, float $mood, int $speed, float $remainder) {
    $user = User::factory()->create(['coins' => 100, 'boarding_plan' => $plan, 'boarding_enabled_at' => now(), 'last_seen_at' => now()]);
    $pet = Pet::factory()->create(['user_id' => $user->id, 'bond' => 100, 'mood' => 100, 'speed' => 50]);

    $this->travel(11)->hours();
    app(PetLifecycle::class)->synchronizeOwner($user);
    $pet->refresh();

    expect($user->fresh()->coins)->toBe(100 - $spent);
    expect($pet->bond)->toBe($bond)->and($pet->mood)->toBe($mood);
    expect($pet->satiety)->toBe((float) $pet->satiety_max)->and($pet->hydration)->toBe((float) $pet->hydration_max);
    expect($pet->health)->toBe((float) $pet->health_max)->and($pet->died_at)->toBeNull();
    expect($pet->speed)->toBe($speed)->and($pet->stat_decay_remainders['speed'])->toEqualWithDelta($remainder, 0.00001);
})->with([
    'cheap' => ['cheap', 11, 89.5, 93.0, 49, 0.05],
    'premium' => ['premium', 33, 94.5, 98.0, 50, 0.35],
]);

test('money runs out at the end of purchased care and normal hunger resumes from that instant', function () {
    $user = User::factory()->create(['coins' => 1, 'boarding_plan' => 'cheap', 'boarding_enabled_at' => now(), 'last_seen_at' => now()]);
    $pet = Pet::factory()->create(['user_id' => $user->id]);

    $this->travel(40)->hours();
    app(PetLifecycle::class)->synchronizeOwner($user);
    $pet->refresh();

    expect($user->fresh()->coins)->toBe(0);
    expect($pet->died_at)->toEqual(now()->subHours(2));
    expect($pet->state_updated_at)->toEqual($pet->died_at);
    expect((int) CurrencyTransaction::query()->where('reason', 'pet_boarding')->sum('amount'))->toBe(-1);
});

test('scarce money is allocated in time order across dogs rather than all to the first dog', function () {
    $user = User::factory()->create(['coins' => 4, 'boarding_plan' => 'cheap', 'boarding_enabled_at' => now(), 'last_seen_at' => now()]);
    $pets = Pet::factory()->count(2)->create(['user_id' => $user->id]);

    $this->travel(4)->hours();
    app(PetLifecycle::class)->synchronizeOwner($user);

    expect($user->fresh()->coins)->toBe(0);
    foreach ($pets as $pet) {
        expect($pet->fresh()->satiety)->toBe(95.0);
    }
    expect(CurrencyTransaction::query()->where('reason', 'pet_boarding')->count())->toBe(2);
});

test('boarding never revives a dog which died before care could start and skips archives', function () {
    $user = User::factory()->create(['coins' => 10, 'boarding_plan' => 'cheap', 'boarding_enabled_at' => now(), 'last_seen_at' => now()]);
    $pet = Pet::factory()->create(['user_id' => $user->id, 'health' => 1, 'satiety' => 0, 'hydration' => 0]);
    Pet::factory()->retired()->create(['user_id' => $user->id]);
    Pet::factory()->deceased()->create(['user_id' => $user->id]);

    $this->travel(2)->hours();
    app(PetLifecycle::class)->synchronizeOwner($user);

    expect($user->fresh()->coins)->toBe(10);
    expect($pet->fresh()->died_at)->toEqual(now()->subHours(2)->addMinutes(12));
    expect(CurrencyTransaction::query()->where('reason', 'pet_boarding')->exists())->toBeFalse();
});

test('changing a boarding plan settles the previous absence first and preserves the already paid hour', function () {
    $user = User::factory()->create(['coins' => 20, 'boarding_plan' => 'cheap', 'boarding_enabled_at' => now(), 'last_seen_at' => now()]);
    $pet = Pet::factory()->create(['user_id' => $user->id]);
    $this->travel(90)->minutes();

    app(UpdatePetBoarding::class)->handle($user, PetBoardingPlan::Off);
    $this->travel(15)->minutes();
    app(PetLifecycle::class)->synchronizeOwner($user);

    expect($user->fresh()->coins)->toBe(19)->and($user->fresh()->boarding_plan)->toBe(PetBoardingPlan::Off);
    expect($pet->fresh()->satiety)->toBe(100.0);

    $this->travel(30)->minutes();
    app(PetLifecycle::class)->synchronizeOwner($user);
    $snapshot = $pet->fresh();
    $snapshot->advanceTo(now(), app(PetDecayCalculator::class));
    expect($snapshot->satiety)->toBe(98.75);
});

test('automatic partial refreshes do not extend player activity but a navigation settles absence before marking return', function () {
    $user = User::factory()->create(['coins' => 10, 'boarding_plan' => 'cheap', 'boarding_enabled_at' => now(), 'last_seen_at' => now()]);
    Pet::factory()->create(['user_id' => $user->id]);
    $initial = $this->actingAs($user)->get(route('dashboard'))->assertOk();
    $idleFrom = now();
    $this->travel(30)->minutes();
    $this->actingAs($user)->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'Dashboard', 'X-Inertia-Partial-Data' => 'pet',
        'X-Inertia-Version' => $initial->viewData('page')['version'], 'X-DogLive-Background' => '1'])
        ->get(route('dashboard'))->assertOk();
    expect($user->fresh()->last_seen_at)->toEqual($idleFrom);

    $this->travel(45)->minutes();
    $this->flushHeaders()->get(route('profile.edit'))->assertOk();

    expect($user->fresh()->last_seen_at)->toEqual(now())->and($user->fresh()->coins)->toBe(9);
});

test('boarding payments and pet coverage roll back together on failure', function () {
    $user = User::factory()->create(['coins' => 10, 'boarding_plan' => 'cheap', 'boarding_enabled_at' => now(), 'last_seen_at' => now()]);
    $pet = Pet::factory()->create(['user_id' => $user->id]);
    $this->travel(2)->hours();

    DB::statement("CREATE FUNCTION reject_boarding() RETURNS trigger LANGUAGE plpgsql AS 'BEGIN RAISE EXCEPTION ''Simulated boarding failure''; END'");
    DB::statement('CREATE TRIGGER reject_boarding BEFORE UPDATE ON pets FOR EACH ROW EXECUTE FUNCTION reject_boarding()');
    try {
        expect(fn () => app(PetLifecycle::class)->synchronizeOwner($user))->toThrow(QueryException::class);
    } finally {
        DB::statement('DROP TRIGGER reject_boarding ON pets');
        DB::statement('DROP FUNCTION reject_boarding()');
    }

    expect($user->fresh()->coins)->toBe(10)->and($pet->fresh()->boarding_periods)->toBeNull();
    expect(CurrencyTransaction::query()->where('reason', 'pet_boarding')->exists())->toBeFalse();
});

test('only valid plans can be saved and guests cannot enable boarding', function () {
    $this->put(route('boarding.update'), ['plan' => 'cheap'])->assertRedirect(route('login'));
    $user = User::factory()->create();
    $this->actingAs($user)->put(route('boarding.update'), ['plan' => 'unknown'])->assertSessionHasErrors('plan');
    $this->put(route('boarding.update'), ['plan' => 'premium'])->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));
    expect($user->fresh()->boarding_plan)->toBe(PetBoardingPlan::Premium);
});

test('event freezing accounts for boarding before taking its historical snapshot without charging later hours', function () {
    $user = User::factory()->create(['coins' => 100, 'boarding_plan' => 'cheap', 'boarding_enabled_at' => now(), 'last_seen_at' => now()]);
    $pet = Pet::factory()->create(['user_id' => $user->id, 'bond' => 100, 'energy' => 100]);
    $closedAt = now()->addHours(20);
    $event = GameEvent::factory()->create([
        'closes_at' => $closedAt, 'starts_at' => $closedAt->addMinutes(15), 'ends_at' => $closedAt->addMinutes(25),
    ]);
    $entry = app(RegisterGameEvent::class)->handle($user, $event->id, $pet->id,
        ['stages' => ['balanced', 'careful', 'bold']], [], 25, (string) Str::uuid());

    $this->travelTo($closedAt);
    app(GameEventProcessor::class)->processForOwner($user);

    expect($entry->fresh()->status)->toBe('frozen');
    expect($entry->fresh()->snapshot['states']['satiety'])->toBe(100);
    expect($pet->fresh()->satiety)->toBe(100.0)->and($pet->fresh()->hydration)->toBe(100.0);
    expect((int) CurrencyTransaction::query()->where('reason', 'pet_boarding')->sum('amount'))->toBe(-20);
    $paid = CurrencyTransaction::query()->where('reason', 'pet_boarding')->count();
    app(GameEventProcessor::class)->processForOwner($user);
    expect(CurrencyTransaction::query()->where('reason', 'pet_boarding')->count())->toBe($paid);
});

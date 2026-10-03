<?php

use App\Models\Disease;
use App\Models\Pet;
use App\Models\PetCareAction;
use App\Models\PetDisease;
use App\Models\PetDiseaseCounter;
use App\Models\User;
use App\Models\VeterinaryVisit;
use App\Modules\Pets\Actions\CompletePetCare;
use App\Modules\Pets\Actions\PurchaseVeterinaryService;
use App\Modules\Pets\Actions\StartPetCare;
use App\Modules\Pets\DTO\PurchaseVeterinaryServiceData;
use App\Modules\Pets\Enums\PetActivity;
use App\Modules\Pets\Enums\VeterinaryService;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Queries\GetPetStatuses;
use App\Modules\Pets\Queries\GetVeterinarian;
use App\Modules\Pets\Services\PetActivityManager;
use App\Modules\Players\Enums\PlayerStatus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->freezeSecond();
});

/** @return array<string, int|string|null> */
function veterinaryPayload(Pet $pet, string $service, ?int $episodeId = null): array
{
    return ['pet_id' => $pet->id, 'service' => $service, 'disease_episode_id' => $episodeId,
        'expected_price' => match ($service) {
            'treatment' => 120, 'checkup' => 60, 'vaccination' => 100
        },
        'token' => (string) Str::uuid()];
}

/** @param array<string, int> $modifiers */
function veterinaryDisease(Pet $pet, array $modifiers = []): PetDisease
{
    $disease = Disease::factory()->create(['name' => ['ru' => 'Болезнь', 'en' => 'Illness'], 'modifiers' => $modifiers]);
    $effect = [...$disease->snapshot(), 'starts_at' => now()->timestamp];
    $episode = PetDisease::factory()->for($pet)->for($disease)->create(['effect_snapshot' => $effect]);
    $pet->update(['debuffs' => [...($pet->debuffs ?? []), $effect]]);

    return $episode;
}

test('the clinic shows an empty state without dogs and never creates data on reads', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('veterinarian.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Veterinarian')->where('clinic.selectedPetId', null)->where('clinic.health', null)
        ->has('clinic.dogs', 0)->has('clinic.diseases', 0)->has('clinic.history', 0)
        ->where('clinic.services.0.price', 120)->where('clinic.services.1.price', 60)->where('clinic.services.2.price', 100));
    $this->assertDatabaseCount('pets', 0);
    $this->assertDatabaseCount('veterinary_visits', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('the clinic selects only owned dogs and keeps causes and counters private', function () {
    $pet = Pet::factory()->create(['health' => 50, 'health_max' => 100]);
    $second = Pet::factory()->for($pet->user)->create();
    $foreign = Pet::factory()->create();
    $episode = veterinaryDisease($pet);
    PetDiseaseCounter::factory()->for($pet)->create(['disease_id' => $episode->disease_id, 'action_count' => 13, 'threshold' => 13]);
    $saved = $pet->fresh()->getAttributes();
    $this->travel(1)->hours();
    $this->actingAs($pet->user)->get(route('veterinarian.index', ['pet' => $pet->id]))->assertInertia(fn (Assert $page) => $page
        ->where('clinic.selectedPetId', $pet->id)->has('clinic.dogs', 2)->has('clinic.diseases', 1)
        ->where('clinic.diseases.0.id', $episode->id)->where('clinic.diseases.0.name', 'Болезнь')
        ->missing('clinic.diseases.0.acquisition_rules')->missing('clinic.diseases.0.threshold')->missing('clinic.diseases.0.action_count'));
    $this->get(route('veterinarian.index', ['pet' => $second->id]))->assertInertia(fn (Assert $page) => $page->has('clinic.diseases', 0));
    $this->get(route('veterinarian.index', ['pet' => $foreign->id]))->assertNotFound();
    expect($pet->fresh()->getAttributes())->toBe($saved);
});

test('treatment cures one disease removes its debuff resets only its counter and charges once', function () {
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500, 'gems' => 20]))->create();
    $episode = veterinaryDisease($pet, ['mood_decay_percent' => 50]);
    $other = veterinaryDisease($pet, ['energy_cost_percent' => 20]);
    $counter = PetDiseaseCounter::factory()->for($pet)->create(['disease_id' => $episode->disease_id, 'action_count' => 16, 'threshold' => 12]);
    $otherCounter = PetDiseaseCounter::factory()->for($pet)->create(['disease_id' => $other->disease_id, 'action_count' => 8]);
    $payload = veterinaryPayload($pet, 'treatment', $episode->id);
    $payload['user_id'] = User::factory()->create()->id;
    $this->actingAs($pet->user)->post(route('veterinarian.store'), $payload)->assertSessionHasNoErrors()
        ->assertRedirect(route('veterinarian.index', ['pet' => $pet->id]));
    $payload['token'] = strtoupper($payload['token']);
    $this->post(route('veterinarian.store'), $payload)->assertSessionHasNoErrors();

    expect($episode->fresh()->ended_at->equalTo(now()))->toBeTrue();
    expect($other->fresh()->ended_at)->toBeNull();
    expect($counter->fresh()->action_count)->toBe(0);
    expect($counter->fresh()->threshold)->toBe(12);
    expect($otherCounter->fresh()->action_count)->toBe(8);
    expect($pet->user->fresh()->coins)->toBe(380);
    expect($pet->user->fresh()->gems)->toBe(20);
    expect($pet->fresh()->debuffs)->toHaveCount(1);
    expect($pet->fresh()->debuffs[0]['disease_id'])->toBe($other->disease_id);
    $this->assertDatabaseCount('veterinary_visits', 1);
    $this->assertDatabaseCount('currency_transactions', 1);
    $this->assertDatabaseHas('currency_transactions', ['user_id' => $pet->user_id, 'currency' => 'coins', 'amount' => -120, 'reason' => 'veterinarian_treatment']);
    $this->post(route('veterinarian.store'), veterinaryPayload($pet, 'treatment', $episode->id))->assertSessionHasErrors('visit');
    $this->assertDatabaseCount('currency_transactions', 1);
});

test('treatment applies offline disease decay until the visit and healthy decay afterwards', function () {
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create(['mood' => 100, 'mood_max' => 100]);
    $episode = veterinaryDisease($pet, ['mood_decay_percent' => 50]);
    $this->travel(2)->hours();
    $this->actingAs($pet->user)->post(route('veterinarian.store'), veterinaryPayload($pet, 'treatment', $episode->id))->assertSessionHasNoErrors();
    expect($pet->fresh()->mood)->toBe(94.0);
    $this->travel(2)->hours();
    expect(app(GetVeterinarian::class)->handle($pet->user, $pet->id, 'en')['health'])->not->toBeNull();
    $this->get(route('dashboard', ['pet' => $pet->id]))->assertInertia(fn (Assert $page) => $page->where('pet.states.mood', 90));
});

test('a cured disease returns only after reaching its threshold again', function () {
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create();
    $episode = veterinaryDisease($pet);
    $episode->disease->update(['is_active' => true, 'acquisition_rules' => ['group' => 'feed', 'variants' => ['meal'], 'daily_min' => 2, 'daily_max' => 2]]);
    $counter = PetDiseaseCounter::factory()->for($pet)->create(['disease_id' => $episode->disease_id,
        'tracked_on' => now()->setTimezone('Europe/Moscow')->toDateString(), 'action_count' => 5, 'threshold' => 2]);
    $this->actingAs($pet->user)->post(route('veterinarian.store'), veterinaryPayload($pet, 'treatment', $episode->id))->assertSessionHasNoErrors();

    foreach ([1, 2] as $count) {
        $activity = app(PetActivityManager::class)->start($pet->user, $pet->id, PetActivity::Feed, now()->addSeconds(30), 0);
        $care = PetCareAction::factory()->create(['pet_id' => $pet->id, 'user_id' => $pet->user_id, 'group' => 'feed', 'variant' => 'meal',
            'activity_token' => $activity->token, 'ends_at' => $activity->endsAt, 'effects' => []]);
        $this->travelTo($care->ends_at);
        app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);
        expect($pet->fresh()->activeDiseaseEpisodes()->count())->toBe($count === 1 ? 0 : 1);
        expect($counter->fresh()->action_count)->toBe($count);
    }
    $this->assertDatabaseCount('pet_diseases', 2);
    $this->assertDatabaseCount('currency_transactions', 1);
});

test('weekly checkups restore health up to the maximum without curing diseases', function (float $health, float $expected) {
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create(['health' => $health, 'health_max' => 200]);
    $episode = veterinaryDisease($pet);
    $payload = veterinaryPayload($pet, 'checkup');
    $this->actingAs($pet->user)->post(route('veterinarian.store'), $payload)->assertSessionHasNoErrors();
    $this->post(route('veterinarian.store'), $payload)->assertSessionHasNoErrors();

    expect($pet->fresh()->health)->toBe($expected);
    expect($episode->fresh()->ended_at)->toBeNull();
    expect($pet->user->fresh()->coins)->toBe(440);
    expect(VeterinaryVisit::query()->sole()->available_at->equalTo(now()->addDays(7)))->toBeTrue();
    $this->assertDatabaseCount('currency_transactions', 1);
})->with(['restore' => [100.0, 120.0], 'cap' => [195.0, 200.0]]);

test('preventive services become available exactly at their saved expiry for each dog', function (string $service, int $days) {
    config(['pet_states.health_loss_per_hour' => 0]);
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 1000]))->create();
    $second = Pet::factory()->for($pet->user)->create();
    $payload = veterinaryPayload($pet, $service);
    $this->actingAs($pet->user)->post(route('veterinarian.store'), $payload)->assertSessionHasNoErrors();
    $deadline = now()->addDays($days);
    $this->post(route('veterinarian.store'), veterinaryPayload($second, $service))->assertSessionHasNoErrors();
    $this->travelTo($deadline->subSecond());
    $this->post(route('veterinarian.store'), veterinaryPayload($pet, $service))->assertSessionHasErrors('visit');
    $this->assertDatabaseCount('veterinary_visits', 2);
    $this->travelTo($deadline);
    $this->post(route('veterinarian.store'), veterinaryPayload($pet, $service))->assertSessionHasNoErrors();
    expect($pet->user->fresh()->coins)->toBe(1000 - 3 * $payload['expected_price']);
    $this->assertDatabaseCount('veterinary_visits', 3);
    $this->assertDatabaseCount('currency_transactions', 3);
})->with(['checkup' => ['checkup', 7], 'vaccination' => ['vaccination', 30]]);

test('vaccination improves actual care health recovery for thirty days without curing diseases', function () {
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create(['health' => 50, 'health_max' => 100, 'energy' => 50]);
    $episode = veterinaryDisease($pet);
    $payload = veterinaryPayload($pet, 'vaccination');
    $this->actingAs($pet->user)->post(route('veterinarian.store'), $payload)->assertSessionHasNoErrors();
    $this->post(route('veterinarian.store'), $payload)->assertSessionHasNoErrors();
    $buff = $pet->fresh()->buffs[0];
    expect($buff)->toMatchArray(['code' => 'veterinarian:vaccination', 'modifiers' => ['health_gain_percent' => 10], 'starts_at' => now()->timestamp, 'expires_at' => now()->addDays(30)->timestamp]);
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'nap', [], (string) Str::uuid());
    expect($care->effects['health'])->toBe(1.1);
    $this->travelTo($care->ends_at);
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);
    expect($episode->fresh()->ended_at)->toBeNull();
    expect($pet->fresh()->health)->toBe(51.1);
    $this->travelTo(now()->setTimestamp($buff['expires_at']));
    expect(app(GetPetStatuses::class)->handle($pet->fresh(), now())->buffs)->toBe([]);
    $this->assertDatabaseCount('currency_transactions', 1);
});

test('unaffordable or stale quotes never apply a veterinary service', function (string $service, int $coins, int $quote) {
    $pet = Pet::factory()->for(User::factory()->state(['coins' => $coins]))->create(['health' => 50]);
    $episode = veterinaryDisease($pet);
    $payload = veterinaryPayload($pet, $service, $service === 'treatment' ? $episode->id : null);
    $payload['expected_price'] = $quote;
    $before = $pet->fresh()->getAttributes();
    $this->actingAs($pet->user)->post(route('veterinarian.store'), $payload)->assertSessionHasErrors('visit');

    expect($pet->user->fresh()->coins)->toBe($coins);
    expect($pet->fresh()->getAttributes())->toBe($before);
    expect($episode->fresh()->ended_at)->toBeNull();
    $this->assertDatabaseCount('currency_transactions', 0);
    $this->assertDatabaseCount('veterinary_visits', 0);
})->with(['treatment poor' => ['treatment', 119, 120], 'checkup poor' => ['checkup', 59, 60],
    'vaccination poor' => ['vaccination', 99, 100], 'treatment changed' => ['treatment', 500, 121],
    'checkup changed' => ['checkup', 500, 61], 'vaccination changed' => ['vaccination', 500, 101]]);

test('the clinic and purchase use the same configured preventive service terms', function (string $service, int $days, int $percentage) {
    config([
        'veterinarian.prices.'.$service => 77,
        'veterinarian.'.$service.'_days' => $days,
        $service === 'checkup' ? 'veterinarian.checkup_health_percent' : 'veterinarian.vaccination_health_gain_percent' => $percentage,
    ]);
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create(['health' => 50, 'health_max' => 100]);
    $clinic = app(GetVeterinarian::class)->handle($pet->user, $pet->id, 'en');
    $quote = collect($clinic['services'])->firstWhere('code', $service);
    expect($quote['price'])->toBe(77);
    expect($clinic[$service === 'checkup' ? 'checkupDays' : 'vaccinationDays'])->toBe($days);
    expect($clinic[$service === 'checkup' ? 'checkupHealth' : 'vaccinationBonus'])->toBe($percentage);
    $payload = [...veterinaryPayload($pet, $service), 'expected_price' => $quote['price']];

    $this->actingAs($pet->user)->post(route('veterinarian.store'), $payload)->assertSessionHasNoErrors();

    expect($pet->user->fresh()->coins)->toBe(423);
    expect(VeterinaryVisit::query()->sole()->available_at->equalTo(now()->addDays($days)))->toBeTrue();
    if ($service === 'checkup') {
        expect($pet->fresh()->health)->toBe(75.0);
    } else {
        expect($pet->fresh()->buffs[0]['modifiers']['health_gain_percent'])->toBe($percentage);
    }
})->with(['checkup' => ['checkup', 3, 25], 'vaccination' => ['vaccination', 12, 18]]);

test('a numeric string in the service configuration cannot authorize a payment', function () {
    config(['veterinarian.prices.checkup' => '60']);
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create(['health' => 50]);
    $clinic = app(GetVeterinarian::class)->handle($pet->user, $pet->id, 'en');
    expect(collect($clinic['services'])->firstWhere('code', 'checkup')['reason'])->toBe('This veterinary service is unavailable.');

    $this->actingAs($pet->user)->post(route('veterinarian.store'), veterinaryPayload($pet, 'checkup'))->assertSessionHasErrors('visit');

    expect($pet->user->fresh()->coins)->toBe(500);
    expect($pet->fresh()->health)->toBe(50.0);
    $this->assertDatabaseCount('currency_transactions', 0);
    $this->assertDatabaseCount('veterinary_visits', 0);
});

test('foreign dogs and foreign disease episodes cannot be treated', function () {
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create();
    $foreign = Pet::factory()->create();
    $episode = veterinaryDisease($foreign);
    $this->actingAs($pet->user)->post(route('veterinarian.store'), veterinaryPayload($foreign, 'treatment', $episode->id))->assertNotFound();
    $this->post(route('veterinarian.store'), veterinaryPayload($pet, 'treatment', $episode->id))->assertSessionHasErrors('visit');
    expect($episode->fresh()->ended_at)->toBeNull();
    expect($pet->user->fresh()->coins)->toBe(500);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('guests blocked players busy and retired dogs cannot purchase services', function () {
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create();
    $payload = veterinaryPayload($pet, 'checkup');
    $this->get(route('veterinarian.index'))->assertRedirect(route('login'));
    $this->post(route('veterinarian.store'), $payload)->assertRedirect(route('login'));
    $pet->user->forceFill(['status' => PlayerStatus::Blocked])->save();
    $this->actingAs($pet->user)->post(route('veterinarian.store'), $payload)->assertForbidden();
    $pet->user->forceFill(['status' => PlayerStatus::Active])->save();
    $pet->update(['activity' => PetActivity::Sleep]);
    $this->post(route('veterinarian.store'), $payload)->assertSessionHasErrors('visit');
    $pet->update(['activity' => null, 'retired_at' => now()]);
    $this->post(route('veterinarian.store'), $payload)->assertSessionHasErrors('visit');
    expect($pet->user->fresh()->coins)->toBe(500);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('request validation rejects invalid services prices tokens and diagnoses', function (array $changes, string $error) {
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create();
    $this->actingAs($pet->user)->post(route('veterinarian.store'), [...veterinaryPayload($pet, 'checkup'), ...$changes])->assertSessionHasErrors($error);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with(['service' => [['service' => 'free_cure'], 'service'], 'price' => [['expected_price' => 0], 'expected_price'],
    'token' => [['token' => 'bad'], 'token'], 'pet' => [['pet_id' => 0], 'pet_id'],
    'missing diagnosis' => [['service' => 'treatment'], 'disease_episode_id'],
    'unexpected diagnosis' => [['disease_episode_id' => 1], 'disease_episode_id']]);

test('a receipt cannot be replayed for another dog service diagnosis or quote', function (array $changes) {
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create();
    $payload = veterinaryPayload($pet, 'treatment', veterinaryDisease($pet)->id);
    $this->actingAs($pet->user)->post(route('veterinarian.store'), $payload)->assertSessionHasNoErrors();
    $this->post(route('veterinarian.store'), [...$payload, ...$changes])->assertSessionHasErrors('visit');
    expect($pet->user->fresh()->coins)->toBe(380);
    $this->assertDatabaseCount('currency_transactions', 1);
})->with(['dog' => [['pet_id' => 999999]], 'service' => [['service' => 'checkup', 'disease_episode_id' => null]],
    'diagnosis' => [['disease_episode_id' => 999999]], 'quote' => [['expected_price' => 121]]]);

test('receipts survive pet deletion and a lost receipt never applies a paid visit again', function () {
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create();
    $payload = veterinaryPayload($pet, 'checkup');
    $this->actingAs($pet->user)->post(route('veterinarian.store'), $payload)->assertSessionHasNoErrors();
    $pet->delete();
    $this->post(route('veterinarian.store'), $payload)->assertSessionHasNoErrors();
    VeterinaryVisit::query()->delete();
    $this->post(route('veterinarian.store'), $payload)->assertSessionHasErrors('visit');
    expect($pet->user->fresh()->coins)->toBe(440);
    $this->assertDatabaseCount('currency_transactions', 1);
});

test('the persisted account status is checked before a new visit or replay', function (bool $replay) {
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create();
    $data = new PurchaseVeterinaryServiceData($pet->id, VeterinaryService::Checkup, null, 60, (string) Str::uuid());
    if ($replay) {
        app(PurchaseVeterinaryService::class)->handle($pet->user, $data);
    }
    User::query()->whereKey($pet->user_id)->update(['status' => PlayerStatus::Blocked]);
    expect(fn () => app(PurchaseVeterinaryService::class)->handle($pet->user, $data))->toThrow(PetUnavailable::class, 'Your account is blocked.');
    expect($pet->user->fresh()->coins)->toBe($replay ? 440 : 500);
})->with([false, true]);

test('receipt failure rolls back payment health statuses disease and counter changes', function (string $service) {
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create(['health' => 50]);
    $episode = veterinaryDisease($pet);
    $counter = PetDiseaseCounter::factory()->for($pet)->create(['disease_id' => $episode->disease_id, 'action_count' => 12]);
    $saved = $pet->fresh()->getAttributes();
    Event::listen('eloquent.creating: '.VeterinaryVisit::class, function (): void {
        throw new RuntimeException('Receipt unavailable');
    });
    expect(fn () => app(PurchaseVeterinaryService::class)->handle($pet->user, new PurchaseVeterinaryServiceData(
        $pet->id, VeterinaryService::from($service), $service === 'treatment' ? $episode->id : null,
        veterinaryPayload($pet, $service)['expected_price'], (string) Str::uuid(),
    )))->toThrow(RuntimeException::class, 'Receipt unavailable');
    expect($pet->user->fresh()->coins)->toBe(500);
    expect($pet->fresh()->getAttributes())->toBe($saved);
    expect($episode->fresh()->ended_at)->toBeNull();
    expect($counter->fresh()->action_count)->toBe(12);
    $this->assertDatabaseCount('currency_transactions', 0);
    $this->assertDatabaseCount('veterinary_visits', 0);
})->with(['treatment', 'checkup', 'vaccination']);

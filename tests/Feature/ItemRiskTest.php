<?php

use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pet;
use App\Models\PetCareAction;
use App\Models\ShopOffer;
use App\Models\StatusEffect;
use App\Models\User;
use App\Modules\Pets\Actions\CompletePetCare;
use App\Modules\Pets\Actions\StartPetCare;
use Database\Seeders\ItemEffectRuleSeeder;
use Database\Seeders\StatusEffectSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Random\Engine;
use Random\Randomizer;

beforeEach(function () {
    $this->withoutVite();
    $this->freezeSecond();
    $this->seed(StatusEffectSeeder::class);
});

function riskItem(Pet $pet, string $category, int $quality): InventoryItem
{
    $instance = InventoryItem::factory()->for($pet->user)->for(Item::factory()->for(
        ItemCategory::factory()->state(['code' => $category]), 'category'
    ))->create(['quality' => $quality, 'remaining_uses' => 1, 'name' => ['ru' => 'Старый предмет', 'en' => 'Old item']]);
    (new ItemEffectRuleSeeder)->seedFor($instance->item);
    $instance->update(['effect_rules' => $instance->item->effectRuleSnapshots()]);

    return $instance;
}

function useRiskDraw(int $draw): Engine
{
    $engine = new class($draw) implements Engine
    {
        public int $calls = 0;

        public function __construct(private int $draw) {}

        public function generate(): string
        {
            $this->calls++;

            return pack('V', $this->draw);
        }
    };
    app()->instance(Randomizer::class, new Randomizer($engine));

    return $engine;
}

test('food risk decreases with inventory quality and the roll respects the exact probability boundary', function (int $quality, int $chance, int $draw, bool $incident) {
    $pet = Pet::factory()->create(['satiety' => 30, 'satiety_max' => 100]);
    $food = riskItem($pet, 'food', $quality);
    $engine = useRiskDraw($draw);
    $response = $this->actingAs($pet->user)->getJson(route('care-items', ['category' => 'food']));
    if ($chance === 0) {
        $response->assertJsonCount(0, 'items.0.risks');
    } else {
        $response->assertJsonPath('items.0.risks.0.chance', $chance);
    }
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'meal', ['food' => $food->id], (string) Str::uuid());

    expect($care->incidents !== null)->toBe($incident);
    expect($engine->calls)->toBe($chance === 0 ? 0 : 1);
})->with([
    'quality 1 hit' => [1, 300, 299, true],
    'quality 1 miss' => [1, 300, 300, false],
    'quality 2' => [2, 250, 249, true],
    'quality 3' => [3, 200, 199, true],
    'quality 4' => [4, 100, 99, true],
    'quality 5 hit' => [5, 50, 49, true],
    'quality 5 miss' => [5, 50, 50, false],
    'quality 6' => [6, 0, 0, false],
    'quality 10' => [10, 0, 0, false],
]);

test('poisoning is saved once hidden until completion and reported with its original item and effect', function () {
    $pet = Pet::factory()->create(['satiety' => 30, 'satiety_max' => 100]);
    $food = riskItem($pet, 'food', 1);
    $engine = useRiskDraw(0);
    $token = (string) Str::uuid();
    $payload = ['variant' => 'meal', 'items' => ['food' => $food->id], 'token' => $token];
    $this->actingAs($pet->user)->post(route('pets.care.store', $pet), $payload)->assertSessionHasNoErrors();
    $this->post(route('pets.care.store', $pet), $payload)->assertSessionHasNoErrors();
    $this->get(route('dashboard'))->assertInertia(fn (Assert $initial) => $initial->reloadOnly(['pet', 'care', 'appearance'], fn (Assert $page) => $page
        ->has('care.recentIncidents', 0)->missing('care.active.incidents')->has('care.debuffs', 0)));
    $this->post(route('pets.care.complete', $pet), ['token' => $token])->assertSessionHasErrors('care');
    StatusEffect::query()->where('code', 'poisoning')->update(['duration_seconds' => 1]);
    $this->travel(30)->seconds();
    $this->followingRedirects()->post(route('pets.care.complete', $pet), ['token' => $token])->assertInertia(fn (Assert $initial) => $initial->hasFlash('toast.type', 'warning')->reloadOnly(['pet', 'care', 'appearance'], fn (Assert $page) => $page

        ->where('care.recentIncidents.0.incidents.0.effect.code', 'poisoning')
        ->where('care.recentIncidents.0.incidents.0.item_name.en', 'Old item')
        ->where('care.debuffs.0.expires_at', now()->addMinutes(30)->timestamp)));
    $snapshot = $pet->fresh()->debuffs;
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $token);
    app(StartPetCare::class)->handle($pet->user, $pet->id, 'meal', ['food' => $food->id], $token);

    expect($engine->calls)->toBe(1);
    expect($pet->fresh()->debuffs)->toBe($snapshot);
    $this->assertModelMissing($food);
    $this->assertDatabaseCount('pet_care_actions', 1);
    $this->assertDatabaseCount('item_usages', 1);
});

test('equipment takes one chafing roll at the highest risk and uses the inventory quality snapshot', function () {
    $pet = Pet::factory()->create();
    $collar = riskItem($pet, 'collars', 1);
    $leash = riskItem($pet, 'leashes', 5);
    $collar->item->update(['quality' => 10]);
    $engine = useRiskDraw(199);
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'walk', ['collars' => $collar->id, 'leashes' => $leash->id], (string) Str::uuid());
    $this->travel(300)->seconds();
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);

    expect($engine->calls)->toBe(1);
    expect($care->incidents)->toHaveCount(1);
    expect($care->incidents[0])->toMatchArray(['chance' => 300, 'quality' => 1]);
    expect($pet->fresh()->debuffs[0]['code'])->toBe('chafing');
});

test('timed debuffs affect later care persist through recovery and disappear at expiry', function () {
    $pet = Pet::factory()->create(['satiety' => 30, 'satiety_max' => 100, 'energy' => 50, 'mood' => 0, 'mood_max' => 100]);
    $food = riskItem($pet, 'food', 1);
    useRiskDraw(0);
    $meal = app(StartPetCare::class)->handle($pet->user, $pet->id, 'meal', ['food' => $food->id], (string) Str::uuid());
    $this->travel(30)->seconds();
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $meal->token);
    $expiresAt = $pet->fresh()->debuffs[0]['expires_at'];
    $play = app(StartPetCare::class)->handle($pet->user, $pet->id, 'attention', [], (string) Str::uuid());
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'energy' => 42]);
    $this->travel(120)->seconds();
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $play->token);
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'mood' => 8]);
    expect($pet->fresh()->debuffs[0]['expires_at'])->toBe($expiresAt);
    $this->travel(1680)->seconds();
    $this->actingAs($pet->user)->get(route('dashboard'))->assertInertia(fn (Assert $initial) => $initial->reloadOnly(['pet', 'care', 'appearance'], fn (Assert $page) => $page
        ->has('care.debuffs', 1)->where('care.debuffs.0.code', 'low_spirits')
        ->has('care.recentIncidents', 1)->where('care.options.4.energy', 6)));
});

test('late automatic completion retains the event history without reviving an expired debuff', function () {
    $pet = Pet::factory()->create(['satiety' => 30, 'satiety_max' => 100]);
    $food = riskItem($pet, 'food', 1);
    useRiskDraw(0);
    app(StartPetCare::class)->handle($pet->user, $pet->id, 'meal', ['food' => $food->id], (string) Str::uuid());
    $this->travel(1830)->seconds();
    $this->actingAs($pet->user)->post(route('pets.care.store', $pet), ['variant' => 'attention', 'items' => [], 'token' => (string) Str::uuid()])->assertSessionHasNoErrors();
    $this->get(route('dashboard'))->assertInertia(fn (Assert $initial) => $initial->reloadOnly(['pet', 'care', 'appearance'], fn (Assert $page) => $page->has('care.debuffs', 0)->has('care.recentIncidents', 1)));
});

test('a failed completion rolls back the incident effect and can be retried without rerolling', function () {
    $pet = Pet::factory()->create(['satiety' => 30, 'satiety_max' => 100]);
    $food = riskItem($pet, 'food', 1);
    $engine = useRiskDraw(0);
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, 'meal', ['food' => $food->id], (string) Str::uuid());
    $this->travel(30)->seconds();
    $this->rejectCareWrites('reject_incident', 'UPDATE');
    expect(fn () => app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token))->toThrow(QueryException::class);
    expect($pet->fresh()->debuffs)->toBe([]);
    $this->assertDatabaseHas('pets', ['id' => $pet->id, 'satiety' => 30, 'activity_token' => $care->activity_token]);
    $this->allowCareWrites('reject_incident');
    app(CompletePetCare::class)->handle($pet->user, $pet->id, $care->token);
    expect($pet->fresh()->debuffs[0]['code'])->toBe('poisoning');
    expect($engine->calls)->toBe(1);
});

test('inactive risks and high quality care products do not draw a random outcome', function (bool $inactive) {
    $pet = Pet::factory()->create(['satiety' => 30, 'satiety_max' => 100, 'cleanliness' => 30, 'cleanliness_max' => 100]);
    if ($inactive) {
        StatusEffect::query()->where('code', 'poisoning')->update(['is_active' => false]);
    }
    $category = $inactive ? 'food' : 'care';
    $item = riskItem($pet, $category, $inactive ? 1 : 6);
    $engine = useRiskDraw(0);
    $care = app(StartPetCare::class)->handle($pet->user, $pet->id, $inactive ? 'meal' : 'care', [$category => $item->id], (string) Str::uuid());
    expect($care->incidents)->toBeNull();
    expect($engine->calls)->toBe(0);
})->with([true, false]);

test('incident history belongs only to the current owners selected pet', function () {
    $owner = User::factory()->create();
    $first = Pet::factory()->for($owner)->create();
    $second = Pet::factory()->for($owner)->create();
    $foreign = Pet::factory()->create();
    $incident = ['effect' => StatusEffect::query()->where('code', 'poisoning')->firstOrFail()->snapshot(), 'chance' => 300, 'item_name' => ['en' => 'Private item'], 'quality' => 1];
    foreach ([$second, $foreign] as $pet) {
        PetCareAction::factory()->create(['user_id' => $pet->user_id, 'pet_id' => $pet->id, 'incidents' => [$incident], 'completed_at' => now()]);
    }
    $this->actingAs($owner)->get(route('dashboard', ['pet' => $first->id]))->assertInertia(fn (Assert $initial) => $initial->reloadOnly(['pet', 'care', 'appearance'], fn (Assert $page) => $page->has('care.recentIncidents', 0)));
});

test('shop and inventory warnings use their own quality values', function () {
    $pet = Pet::factory()->create();
    $food = riskItem($pet, 'food', 5);
    $food->item->update(['quality' => 1]);
    ShopOffer::factory()->for($food->item)->create();
    $this->actingAs($pet->user)->get(route('shop.index'))->assertInertia(fn (Assert $page) => $page->where('offers.0.risks.0.chance', 300));
    $food->item->update(['quality' => 10]);
    $this->get(route('shop.index'))->assertInertia(fn (Assert $page) => $page->has('offers.0.risks', 0));
    $this->get(route('inventory.index'))->assertInertia(fn (Assert $page) => $page->where('items.0.risks.0.chance', 50));
});

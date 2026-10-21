<?php

use App\Models\AdminAuditLog;
use App\Models\Dog;
use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\PetHistoryEntry;
use App\Models\PetSportRecord;
use App\Models\PetTitle;
use App\Models\User;
use App\Modules\Pets\Actions\PublishPetSale;
use App\Modules\Pets\Actions\PurchasePetSale;
use App\Modules\Pets\Actions\RetirePet;
use App\Modules\Pets\DTO\PetTitleData;
use App\Modules\Pets\Queries\GetPetEventProfile;
use App\MoonShine\Enums\StaffRole;
use App\MoonShine\Resources\DogResource;
use App\MoonShine\Resources\PetResource;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;

function eventProfilePedigree(Dog $breed, int $remaining): Pet
{
    if ($remaining === 0) {
        return Pet::factory()->for($breed)->create(['user_id' => null]);
    }

    return Pet::factory()->for($breed)->create([
        'father_id' => eventProfilePedigree($breed, $remaining - 1)->id,
        'mother_id' => eventProfilePedigree($breed, $remaining - 1)->id,
    ]);
}

function eventProfileStaff(StaffRole $role): MoonshineUser
{
    return MoonshineUser::factory()->create([
        'moonshine_user_role_id' => MoonshineUserRole::query()->where('code', $role->value)->value('id'),
    ]);
}

function eventProfileEntry(Pet $pet, int $hours): GameEventEntry
{
    return GameEventEntry::factory()->for($pet)->create(['game_event_id' => GameEvent::factory()->create([
        'starts_at' => now()->addHours($hours), 'ends_at' => now()->addHours($hours)->addMinutes(10),
    ])->id]);
}

test('known breed puppies receive a stable physical profile without invented credentials', function () {
    $breed = Dog::factory()->create(['breed' => 'german_shepherd']);
    $pet = Pet::factory()->for($breed)->create(['born_at' => now()->subDays(3)]);

    expect($pet->fresh()->event_profile['height_cm'])->toBeGreaterThanOrEqual(60)->toBeLessThanOrEqual(65);
    $profile = app(GetPetEventProfile::class)->forPet($pet->fresh());

    expect($profile)->toMatchArray([
        'recognized_rkf' => true, 'recognized_fci' => true, 'fci_group' => 1,
        'working_breed' => true, 'registered' => true, 'pedigree_complete' => false,
        'working_certificate' => false, 'height_category' => 'L', 'race_category' => '3',
        'agility_level' => 1, 'agility_available_levels' => [1], 'championships' => [],
    ]);
    expect(app(GetPetEventProfile::class)->forPet($pet->fresh())['height_cm'])->toBe($profile['height_cm']);
});

test('agility height category uses the individual height at each official boundary', function (float $height, string $category) {
    $pet = Pet::factory()->create(['event_profile' => ['height_cm' => $height]]);

    expect(app(GetPetEventProfile::class)->forPet($pet)['height_category'])->toBe($category);
    expect($pet->fresh()->event_profile['height_cm'])->toEqual($height);
})->with([
    'small' => [34.9, 'S'], 'medium lower' => [35.0, 'M'], 'medium upper' => [42.9, 'M'],
    'intermediate lower' => [43.0, 'I'], 'intermediate upper' => [47.9, 'I'], 'large' => [48.0, 'L'],
]);

test('an unknown purebred catalogue entry is not automatically recognized or registered', function () {
    $pet = Pet::factory()->create(['is_purebred' => true]);

    expect(app(GetPetEventProfile::class)->forPet($pet))->toMatchArray([
        'recognized_rkf' => false, 'recognized_fci' => false, 'fci_group' => null,
        'registered' => false, 'pedigree_complete' => false, 'race_category' => '4',
    ]);
});

test('club registry is explicit and old pet metadata falls back to the known breed registry without rewriting the profile', function () {
    $breed = Dog::factory()->create(['breed' => 'german_shepherd']);
    $pet = Pet::factory()->for($breed)->create();
    $historical = $pet->event_profile;
    unset($historical['club_system']);
    $pet->event_profile = $historical;
    $pet->saveQuietly();

    $profile = app(GetPetEventProfile::class)->forPet($pet->fresh());

    expect($profile)->toMatchArray(['club_system' => 'nkp', 'has_nkp' => true]);
    expect($pet->fresh()->event_profile)->toBe($historical);
});

test('working admission requires a verified credential and does not infer it from unrelated awards', function () {
    $pet = Pet::factory()->create(['is_purebred' => true]);
    PetTitle::factory()->for($pet)->create(['code' => 'agility_monthly_winner']);
    expect(app(GetPetEventProfile::class)->forPet($pet)['working_certificate'])->toBeFalse();

    $pet->forceFill(['event_profile' => [...$pet->event_profile, 'working_certificate_verified' => true]])->save();

    expect(app(GetPetEventProfile::class)->forPet($pet)['working_certificate'])->toBeTrue();
});

test('a full three generation pedigree qualifies while purity and a partial tree do not', function () {
    $breed = Dog::factory()->create(['breed' => 'german_shepherd']);
    $partial = Pet::factory()->for($breed)->create(['father_id' => Pet::factory()->for($breed)->create()->id]);
    $complete = eventProfilePedigree($breed, 3);

    expect(app(GetPetEventProfile::class)->forPet($partial)['pedigree_complete'])->toBeFalse();
    expect(app(GetPetEventProfile::class)->forPet($complete)['pedigree_complete'])->toBeTrue();
});

test('cycles cannot manufacture a complete pedigree but an administrator can verify external papers', function () {
    $pet = Pet::factory()->create();
    $pet->update(['father_id' => $pet->id, 'mother_id' => $pet->id]);
    expect(app(GetPetEventProfile::class)->forPet($pet)['pedigree_complete'])->toBeFalse();

    $pet->forceFill(['event_profile' => [...$pet->event_profile, 'pedigree_verified' => true]])->save();

    expect(app(GetPetEventProfile::class)->forPet($pet)['pedigree_complete'])->toBeTrue();
});

test('the event passport distinguishes old awards from official championships', function () {
    $pet = Pet::factory()->create();
    $legacy = PetTitle::factory()->for($pet)->create(['code' => 'CH_RUS']);
    $champion = PetTitle::factory()->for($pet)->create([
        'game_event_entry_id' => eventProfileEntry($pet, 2)->id,
        'kind' => 'championship', 'discipline' => 'conformation', 'code' => 'CH_RKF', 'details' => ['certificates' => [11, 12]],
        'regulation_version' => 'rkf-2026',
    ]);

    expect($legacy->fresh()->kind)->toBe('legacy');
    expect(app(GetPetEventProfile::class)->forPet($pet)['championships'])->toBe(['CH_RKF']);
    expect(PetTitleData::fromTitle($champion->fresh(), 'en'))->toMatchArray([
        'kind' => 'championship', 'details' => ['certificates' => [11, 12]], 'regulationVersion' => 'rkf-2026',
    ]);
});

test('the same championship cannot be awarded to one dog through different entries', function () {
    $pet = Pet::factory()->create();
    PetTitle::factory()->for($pet)->create(['kind' => 'championship', 'code' => 'CH_RKF']);

    $secondEntry = eventProfileEntry($pet, 2);
    expect(fn () => DB::transaction(fn () => PetTitle::factory()->for($pet)->create(['game_event_entry_id' => $secondEntry->id, 'kind' => 'championship', 'code' => 'CH_RKF'])))
        ->toThrow(QueryException::class);

    expect($pet->titles()->where('kind', 'championship')->count())->toBe(1);
});

test('sport admission reads earned flat regulatory progress instead of legacy wins or tier', function () {
    $pet = Pet::factory()->create();
    PetSportRecord::factory()->for($pet)->create([
        'discipline' => 'agility', 'starts' => 100, 'wins' => 100, 'tier' => 2,
        'regulatory_progress' => ['agility_level' => 2, 'agility_available_levels' => [1, 2]],
    ]);
    PetSportRecord::factory()->for($pet)->create([
        'discipline' => 'nosework', 'regulatory_progress' => [
            'nosework_levels' => ['pr1' => 2, 'pr2' => 1],
            'nosework_tests' => ['pr1' => ['birch', 'lavender'], 'pr2' => ['cinnamon']],
            'nosework_nw0_qualifications' => ['pr1' => 2, 'pr2' => 0],
        ],
    ]);

    expect(app(GetPetEventProfile::class)->forPet($pet))->toMatchArray([
        'agility_level' => 2, 'agility_available_levels' => [1, 2],
        'nosework_levels' => ['pr1' => 2, 'pr2' => 1],
        'nosework_tests' => ['pr1' => ['birch', 'lavender'], 'pr2' => ['cinnamon']],
        'nosework_nw0_qualifications' => ['pr1' => 2, 'pr2' => 0],
    ]);
});

test('selling a dog preserves its physical profile and earned regulatory progression', function () {
    $this->freezeSecond();
    $seller = User::factory()->create();
    $buyer = User::factory()->create(['coins' => 500]);
    $pet = Pet::factory()->for($seller)->create(['event_profile' => [
        'height_cm' => 43.2, 'registered' => true, 'pedigree_verified' => true,
        'working_certificate_verified' => true, 'race_category' => '3',
    ]]);
    PetSportRecord::factory()->for($pet)->create(['regulatory_progress' => ['agility_level' => 3, 'agility_available_levels' => [3]]]);
    $before = app(GetPetEventProfile::class)->forPet($pet);
    $listing = app(PublishPetSale::class)->handle($seller, $pet->id, 100);

    app(PurchasePetSale::class)->handle($buyer, $listing->id, 100, (string) Str::uuid());

    expect(app(GetPetEventProfile::class)->forPet($pet->fresh()))->toBe($before);
    expect($pet->fresh()->user_id)->toBe($buyer->id);
});

test('an administrator can verify event papers while tampered game attributes are ignored and audited', function () {
    $pet = Pet::factory()->create(['health' => 100, 'speed' => 25]);
    $admin = eventProfileStaff(StaffRole::Administrator);
    $resource = app(PetResource::class);

    $this->actingAs($admin, 'moonshine')->put(route('moonshine.crud.update', [
        'resourceUri' => $resource->getUriKey(), 'resourceItem' => $pet->id,
    ]), [
        'event_profile' => ['height_cm' => 43.2, 'registered' => 1, 'pedigree_verified' => 1,
            'working_certificate_verified' => 1, 'race_category' => '3'],
        'health' => 1, 'speed' => 9999, 'retired_at' => now()->toIso8601String(),
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($pet->fresh()->event_profile['working_certificate_verified'])->toBeTrue();
    expect($pet->fresh()->health)->toBe(100.0);
    expect($pet->fresh()->speed)->toBe(25);
    expect($pet->fresh()->retired_at)->toBeNull();
    expect(AdminAuditLog::query()->sole()->changes)->toHaveKey('event_profile')->not->toHaveKeys(['health', 'speed', 'retired_at']);
});

test('a moderator cannot grant event credentials through the pet update endpoint', function () {
    $pet = Pet::factory()->create();

    $this->actingAs(eventProfileStaff(StaffRole::Moderator), 'moonshine')->put(route('moonshine.crud.update', [
        'resourceUri' => app(PetResource::class)->getUriKey(), 'resourceItem' => $pet->id,
    ]), ['event_profile' => ['working_certificate_verified' => true]])->assertForbidden();

    expect($pet->fresh()->event_profile['working_certificate_verified'])->toBeFalse();
    $this->assertDatabaseCount('admin_audit_logs', 0);
});

test('event winning titles repeat while all earned championship codes remain available in event snapshots', function () {
    $pet = Pet::factory()->create();
    PetTitle::factory()->for($pet)->create(['kind' => 'championship', 'code' => 'SPORT_CH_RUS', 'discipline' => 'agility']);
    PetTitle::factory()->for($pet)->create([
        'game_event_entry_id' => eventProfileEntry($pet, 2)->id,
        'kind' => 'championship', 'code' => 'SPORT_CH_RUS', 'discipline' => 'nosework',
    ]);
    PetTitle::factory()->for($pet)->create([
        'game_event_entry_id' => eventProfileEntry($pet, 3)->id,
        'kind' => 'championship', 'code' => 'SPORT_CH_RUS', 'discipline' => 'agility',
    ]);

    expect($pet->titles()->where('kind', 'championship')->count())->toBe(3);
    expect(app(GetPetEventProfile::class)->forPet($pet)['championships'])->toBe(['SPORT_CH_RUS']);
});

test('a regulated sporting field can retain integer places beyond the legacy smallint limit', function () {
    $entry = GameEventEntry::factory()->create(['rank' => 40000]);
    $title = PetTitle::factory()->for($entry, 'entry')->create(['rank' => 40000]);

    expect($entry->fresh()->rank)->toBe(40000);
    expect($title->fresh()->rank)->toBe(40000);
});

test('custom breed metadata and physical ranges are editable without changing existing dog snapshots', function () {
    $breed = Dog::factory()->create();
    $existing = Pet::factory()->for($breed)->create();
    $before = app(GetPetEventProfile::class)->forPet($existing);
    $payload = $breed->only(['name', 'is_starter', 'endurance_potential', 'speed_potential', 'strength_potential', 'agility_potential', 'obedience_potential', 'intelligence_potential', 'health_max', 'energy_max', 'satiety_max', 'hydration_max', 'mood_max', 'cleanliness_max', 'bond_max', 'food_per_day', 'water_per_day']);
    $payload['event_profile'] = [
        'recognized_rkf' => 1, 'recognized_fci' => 1, 'fci_group' => 3, 'working_breed' => 0,
        'height_ranges' => ['male' => [34, 36], 'female' => [43, 47]],
    ];
    $this->actingAs(eventProfileStaff(StaffRole::Administrator), 'moonshine')->put(route('moonshine.crud.update', [
        'resourceUri' => app(DogResource::class)->getUriKey(), 'resourceItem' => $breed->id,
    ]), $payload)->assertRedirect()->assertSessionHasNoErrors();

    $newPet = Pet::factory()->for($breed->fresh())->create(['sex' => 'female']);
    expect(app(GetPetEventProfile::class)->forPet($newPet))->toMatchArray([
        'recognized_rkf' => true, 'recognized_fci' => true, 'fci_group' => 3, 'height_category' => 'I',
    ]);
    expect(app(GetPetEventProfile::class)->forPet($existing->fresh()))->toBe($before);
    expect(AdminAuditLog::query()->sole()->changes)->toHaveKey('event_profile');
});

test('unlisted credential keys cannot be smuggled through the trusted pet editor', function () {
    $pet = Pet::factory()->create();
    $before = $pet->event_profile;

    $this->actingAs(eventProfileStaff(StaffRole::Administrator), 'moonshine')->put(route('moonshine.crud.update', [
        'resourceUri' => app(PetResource::class)->getUriKey(), 'resourceItem' => $pet->id,
    ]), ['event_profile' => [
        'height_cm' => 43.2, 'registered' => 1, 'pedigree_verified' => 1,
        'working_certificate_verified' => 1, 'race_category' => '3', 'recognized_fci' => true,
    ]])->assertSessionHasErrors('event_profile', null, app(PetResource::class)->getUriKey());

    expect($pet->fresh()->event_profile)->toBe($before);
    $this->assertDatabaseCount('admin_audit_logs', 0);
});

test('only proven automatic career flags are restored by the migration', function () {
    $this->freezeSecond();
    $automatic = Pet::factory()->create(['retired_at' => now()->subHour()]);
    $manual = Pet::factory()->create(['retired_at' => now()->subHour()]);
    $unknown = Pet::factory()->create(['retired_at' => now()->subHour()]);
    $mismatch = Pet::factory()->create(['retired_at' => now()->subHour()]);
    foreach ([[$automatic, true, now()->subHour()], [$manual, false, now()->subHour()], [$mismatch, true, now()->subHours(2)]] as [$pet, $flag, $at]) {
        PetHistoryEntry::factory()->create([
            'pet_id' => $pet->id, 'event_code' => 'life.retirement', 'details' => ['automatic' => $flag], 'occurred_at' => $at,
        ]);
    }
    $migration = require database_path('migrations/2026_10_20_175454_restore_automatically_retired_pet_careers.php');
    $migration->up();
    $migration->up();

    expect($automatic->fresh()->retired_at)->toBeNull();
    expect($manual->fresh()->retired_at)->toEqual(now()->subHour());
    expect($unknown->fresh()->retired_at)->toEqual(now()->subHour());
    expect($mismatch->fresh()->retired_at)->toEqual(now()->subHour());
});

test('a restored automatic retirement does not hide a later voluntary decision from the history', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['retired_at' => now()->subHour()]);
    PetHistoryEntry::factory()->create([
        'pet_id' => $pet->id, 'event_code' => 'life.retirement', 'source_key' => 'life.retirement:'.$pet->id,
        'details' => ['automatic' => true], 'occurred_at' => now()->subHour(),
    ]);
    $migration = require database_path('migrations/2026_10_20_175454_restore_automatically_retired_pet_careers.php');
    $migration->up();

    app(RetirePet::class)->handle($pet->user, $pet->id);

    expect($pet->fresh()->retired_at)->toEqual(now());
    expect(PetHistoryEntry::query()->where('pet_id', $pet->id)->where('event_code', 'life.retirement')->count())->toBe(2);
    expect(PetHistoryEntry::query()->where('pet_id', $pet->id)->where('details->automatic', false)->sole()->occurred_at)->toEqual(now());
});

<?php

use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\PetTitle;
use App\Models\PetTitleConsumption;
use App\Modules\Pets\Queries\GetPetCareer;
use App\Modules\Pets\Services\EventTitleRecorder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/** @param array<string,mixed> $attributes */
function consumptionTitle(Pet $pet, array $attributes): PetTitle
{
    $at = now()->addHours(GameEvent::query()->count() + 1);
    $event = GameEvent::factory()->create([
        'registration_opens_at' => $at->subDay(), 'closes_at' => $at->subMinutes(15),
        'starts_at' => $at, 'ends_at' => $at->addMinutes(10),
    ]);
    $entry = GameEventEntry::factory()->for($pet)->for($pet->user)->for($event, 'event')->create();

    return PetTitle::factory()->for($pet)->for($entry, 'entry')->create($attributes);
}

test('championship identity distinguishes document sets while career groups repeat regional diplomas by subject', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create();
    foreach ([['moscow', 'first'], ['moscow', 'second'], ['novosibirsk', 'third']] as $index => [$region, $issue]) {
        $at = now()->subHours(4 - $index);
        $event = GameEvent::factory()->create([
            'discipline' => 'agility', 'status' => 'settled',
            'registration_opens_at' => $at->subDay(), 'closes_at' => $at->subMinutes(20),
            'starts_at' => $at->subMinutes(10), 'ends_at' => $at, 'settled_at' => $at,
        ]);
        $entry = GameEventEntry::factory()->for($pet)->for($pet->user)->for($event, 'event')->create([
            'status' => 'completed', 'completed_at' => $at, 'rank' => 1, 'result' => ['eliminated' => false],
        ]);
        PetTitle::factory()->for($pet)->for($entry, 'entry')->create([
            'discipline' => 'agility', 'code' => 'SPORT_REGIONAL_CH', 'kind' => 'championship',
            'scope_key' => 'region:'.$region.':'.$issue, 'regulation_version' => '2',
            'details' => ['region' => $region, 'credential_title_ids' => [101, 102, 103]], 'awarded_at' => $at,
        ]);
    }

    $career = app(GetPetCareer::class)->handle($pet->user, $pet->id, 'ru');

    expect($career['titles'])->toHaveCount(2);
    expect(collect($career['titles'])->firstWhere('name', 'Региональный чемпион по рабочим качествам · Москва'))
        ->toMatchArray(['count' => 2, 'scopeKey' => 'region:moscow:second', 'sourceCount' => 3]);
    expect(collect($career['titles'])->firstWhere('name', 'Региональный чемпион по рабочим качествам · Новосибирская область'))
        ->toMatchArray(['count' => 1]);
});

test('the same scoped championship cannot be awarded through a different event entry', function () {
    $pet = Pet::factory()->create();
    consumptionTitle($pet, ['kind' => 'championship', 'code' => 'SPORT_REGIONAL_CH', 'scope_key' => 'region:moscow:first']);

    expect(fn () => DB::transaction(fn () => consumptionTitle($pet, [
        'kind' => 'championship', 'code' => 'SPORT_REGIONAL_CH', 'scope_key' => 'region:moscow:first',
    ])))->toThrow(QueryException::class);

    expect($pet->titles()->where('code', 'SPORT_REGIONAL_CH')->count())->toBe(1);
});

test('one triggering entry can issue separate regional diplomas with different supporting documents', function () {
    $pet = Pet::factory()->create();
    $first = consumptionTitle($pet, ['kind' => 'certificate', 'code' => 'REG_CACAg']);
    $second = consumptionTitle($pet, ['kind' => 'certificate', 'code' => 'REG_CACAg']);
    $trigger = $second->entry;
    $event = $trigger->event;
    $recorder = app(EventTitleRecorder::class);
    $firstAward = [
        'code' => 'SPORT_REGIONAL_CH', 'kind' => 'championship', 'scope_key' => 'region:moscow:first',
        'details' => ['region' => 'moscow', 'credential_title_ids' => [$first->id], 'proof_purpose' => 'sport_exchange'],
    ];
    $secondAward = [
        'code' => 'SPORT_REGIONAL_CH', 'kind' => 'championship', 'scope_key' => 'region:moscow:second',
        'details' => ['region' => 'moscow', 'credential_title_ids' => [$second->id], 'proof_purpose' => 'sport_exchange'],
    ];

    $issued = DB::transaction(fn (): array => [
        $recorder->record($event, $trigger, $pet, $firstAward),
        $recorder->record($event, $trigger, $pet, $secondAward),
        $recorder->record($event, $trigger, $pet, $firstAward),
    ]);

    expect($issued[0]->id)->not->toBe($issued[1]->id);
    expect($issued[2]->id)->toBe($issued[0]->id);
    expect($trigger->titles()->where('code', 'SPORT_REGIONAL_CH')->count())->toBe(2);
    $this->assertDatabaseCount('pet_title_consumptions', 2);
});

test('a certificate can support only one diploma per exchange purpose', function () {
    $pet = Pet::factory()->create();
    $certificate = consumptionTitle($pet, ['kind' => 'certificate', 'code' => 'CACAg']);
    $first = consumptionTitle($pet, ['kind' => 'championship', 'code' => 'SPORT_REGIONAL_CH', 'scope_key' => 'first']);
    $second = consumptionTitle($pet, ['kind' => 'championship', 'code' => 'SPORT_REGIONAL_CH', 'scope_key' => 'second']);
    $use = PetTitleConsumption::factory()->for($certificate, 'credential')->for($first, 'championship')->create(['purpose' => 'sport_exchange']);

    expect(fn () => DB::transaction(fn () => PetTitleConsumption::factory()->for($certificate, 'credential')->for($second, 'championship')->create([
        'purpose' => 'sport_exchange',
    ])))->toThrow(QueryException::class);

    expect($certificate->consumptions->modelKeys())->toBe([$use->id]);
    expect($first->proofs->modelKeys())->toBe([$use->id]);
    $this->assertDatabaseCount('pet_title_consumptions', 1);
});

test('independent show diploma exchanges retain their own use of a certificate', function () {
    $pet = Pet::factory()->create();
    $certificate = consumptionTitle($pet, ['kind' => 'certificate', 'code' => 'CAC']);
    $first = consumptionTitle($pet, ['kind' => 'championship', 'code' => 'GCH_RKF', 'scope_key' => 'first']);
    $second = consumptionTitle($pet, ['kind' => 'championship', 'code' => 'ACH_RKF', 'scope_key' => 'second']);

    PetTitleConsumption::factory()->for($certificate, 'credential')->for($first, 'championship')->create(['purpose' => 'show:GCH_RKF']);
    PetTitleConsumption::factory()->for($certificate, 'credential')->for($second, 'championship')->create(['purpose' => 'show:ACH_RKF']);

    expect($certificate->consumptions()->count())->toBe(2);
});

test('issued diplomas and their source certificates cannot be deleted while referenced', function (string $relation) {
    $pet = Pet::factory()->create();
    $certificate = consumptionTitle($pet, ['kind' => 'certificate', 'code' => 'CAC']);
    $championship = consumptionTitle($pet, ['kind' => 'championship', 'code' => 'GCH_RKF']);
    $use = PetTitleConsumption::factory()->for($certificate, 'credential')->for($championship, 'championship')->create();

    expect(fn () => DB::transaction(fn () => $use->{$relation}->delete()))->toThrow(QueryException::class);

    $this->assertModelExists($certificate);
    $this->assertModelExists($championship);
})->with(['source certificate' => ['credential'], 'issued diploma' => ['championship']]);

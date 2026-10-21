<?php

use App\Models\Dog;
use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\PetTitle;
use App\Models\User;
use App\Modules\Pets\Calculators\GameEventRandomness;
use App\Modules\Pets\Queries\GetGameEvents;
use App\Modules\Pets\Services\GameEventAdmission;
use App\Modules\Pets\Services\GameEventProcessor;

function canicrossQualificationEvent(): GameEvent
{
    return GameEvent::factory()->create([
        'discipline' => 'canicross', 'status' => 'frozen', 'seed' => 'canicross-qualification',
        'rules' => ['version' => 3, 'fee' => 25, 'prizes' => [100, 60, 40], 'energy_cost' => 30, 'field_size' => 1,
            'stages' => ['start', 'course', 'finish'],
            'regulation' => ['version' => 2, 'system' => 'rkf', 'rank' => 'qualifying', 'format' => 'canicross']],
    ]);
}

function canicrossQualificationEntry(GameEvent $event, Pet $pet, int $stat): GameEventEntry
{
    $plan = ['sport_class' => 'canicross_open', 'handler_category' => 'open', 'stages' => array_fill(0, 3, 'careful')];
    $snapshot = app(GameEventAdmission::class)->snapshot($event, $pet, $plan, []);
    $snapshot['stats'] = array_fill_keys(array_keys($snapshot['stats']), $stat);
    $snapshot['states'] = array_fill_keys(array_keys($snapshot['states']), 100);
    $snapshot['profile']['race_category'] = '3';
    $randomness = new GameEventRandomness;
    $attempt = 0;
    do {
        $token = $randomness->token('canicross-clear:'.$pet->id.':'.$attempt++);
        $draws = $randomness->draws($event->seed.':entry:'.$token, 6);
    } while (! collect($draws)->every(fn (float $draw): bool => $draw > 0.8));

    return GameEventEntry::factory()->for($event, 'event')->for($pet)->for($pet->user)->create([
        'status' => 'frozen', 'division' => 'canicross_open:3:open', 'snapshot' => $snapshot,
        'plan' => $plan, 'operation_token' => $token,
    ]);
}

test('canicross settlement records the actual qualification without removing a slower finishers placement', function () {
    $this->freezeSecond();
    $event = canicrossQualificationEvent();
    $breed = Dog::factory()->create();
    $entries = collect([1000000, 1000000, 0])->map(function (int $stat) use ($event, $breed): GameEventEntry {
        $pet = Pet::factory()->for($breed)->for(User::factory()->state(['coins' => 0]))->create();

        return canicrossQualificationEntry($event, $pet, $stat);
    });
    $snapshots = $entries->map(fn (GameEventEntry $entry): array => $entry->snapshot)->all();
    $this->travelTo($event->ends_at);

    app(GameEventProcessor::class)->processDue();
    app(GameEventProcessor::class)->processDue();

    $entries->each->refresh();
    expect($entries->map(fn (GameEventEntry $entry): mixed => $entry->result['race_qualified'] ?? null)->all())->toBe([true, true, false]);
    expect($entries->map(fn (GameEventEntry $entry): array => $entry->snapshot)->all())->toBe($snapshots);
    $slower = $entries->last();
    expect($slower->result['qualified'])->toBeTrue();
    expect($slower->rank)->toBe(3);
    expect($slower->prize)->toBe(40);
    expect($slower->user->fresh()->coins)->toBe(40);
    $this->assertDatabaseCount('currency_transactions', 3);
    $this->assertDatabaseMissing('pet_titles', ['game_event_entry_id' => $slower->id, 'code' => 'RACE_QUALIFICATION']);
    $state = app(GetGameEvents::class)->showState($slower->user, $event, 'en');
    expect($state['entry']['result']['assessment'])->toBe(['code' => 'not_qualified', 'label' => 'Not qualified', 'qualified' => false]);
});

test('one canicross starter keeps its win and prize without earning a qualification', function () {
    $this->freezeSecond();
    $event = canicrossQualificationEvent();
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 0]))->create();
    $entry = canicrossQualificationEntry($event, $pet, 1000000);
    $this->travelTo($event->ends_at);

    app(GameEventProcessor::class)->processDue();

    $entry->refresh();
    expect($entry->result['race_qualified'] ?? null)->toBeFalse();
    expect($entry->result['qualified'])->toBeTrue();
    expect($entry->rank)->toBe(1);
    expect($pet->user->fresh()->coins)->toBe(100);
    $this->assertDatabaseMissing('pet_titles', ['game_event_entry_id' => $entry->id, 'code' => 'RACE_QUALIFICATION']);
});

test('canicross assessments use saved qualification or a matching historical credential', function (array $qualification, bool $hasCredential, string $code, string $label, bool $qualified, string $locale) {
    $this->freezeSecond();
    $event = canicrossQualificationEvent();
    $event->update(['status' => 'settled']);
    $pet = Pet::factory()->create();
    $entry = GameEventEntry::factory()->for($event, 'event')->for($pet)->for($pet->user)->create([
        'status' => 'completed', 'division' => 'canicross_open:3:open', 'rank' => 1,
        'result' => ['version' => 3, 'qualified' => true, 'eliminated' => false, 'grade' => null, 'stages' => [], ...$qualification],
    ]);
    if ($hasCredential) {
        PetTitle::factory()->for($entry, 'entry')->for($pet)->create([
            'code' => 'RACE_QUALIFICATION', 'kind' => 'qualification', 'discipline' => 'canicross',
        ]);
    }

    $state = app(GetGameEvents::class)->showState($pet->user, $event, $locale);

    expect($state['entry']['result']['assessment'])->toBe(['code' => $code, 'label' => $label, 'qualified' => $qualified]);
    expect($state['entry']['result']['qualified'])->toBeTrue();
})->with([
    'saved successful qualification' => [['race_qualified' => true], false, 'qualified', 'Qualified', true, 'en'],
    'saved failed qualification' => [['race_qualified' => false], false, 'not_qualified', 'Not qualified', false, 'en'],
    'saved result remains authoritative' => [['race_qualified' => false], true, 'not_qualified', 'Not qualified', false, 'en'],
    'old result with qualification credential' => [[], true, 'qualified', 'Qualified', true, 'en'],
    'old result without qualification evidence' => [[], false, 'finished', 'Finished', false, 'en'],
    'old result without qualification evidence in Russian' => [[], false, 'finished', 'Финишировал', false, 'ru'],
]);

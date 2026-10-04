<?php

use App\Models\CurrencyTransaction;
use App\Models\Dog;
use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Actions\CancelGameEventEntry;
use App\Modules\Pets\Actions\RegisterGameEvent;
use App\Modules\Pets\DTO\GameEventDivisionData;
use App\Modules\Pets\Services\GameEventProcessor;
use Illuminate\Support\Str;

function registerDogInHeat(Pet $pet, GameEvent $event): GameEventEntry
{
    return app(RegisterGameEvent::class)->handle($pet->user, $event->id, $pet->id,
        ['stages' => ['careful', 'careful', 'careful']], [], $event->rules['fee'], (string) Str::uuid());
}

test('new heats admit more players reuse cancelled places and settle independently', function () {
    $this->freezeSecond();
    $event = GameEvent::factory()->create(['seed' => 'multiple-heats']);
    $entries = collect();
    foreach (range(1, 9) as $index) {
        $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create(['size' => 'small']);
        $entries->push(registerDogInHeat($pet, $event));
        $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'coins' => 475]);
    }
    expect($entries->take(8)->pluck('division')->unique()->all())->toBe(['novice:small']);
    expect($entries->last()->division)->toBe('novice:small:heat-2');
    $cancelled = $entries->first();
    app(CancelGameEventEntry::class)->handle($cancelled->user, $cancelled->id);
    $replacement = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create(['size' => 'small']);
    expect(registerDogInHeat($replacement, $event)->division)->toBe('novice:small');
    $this->travelTo($event->closes_at);
    $processor = app(GameEventProcessor::class);

    $processor->processDue();

    $groups = $event->entries()->where('status', 'frozen')->get()->groupBy('division');
    expect($groups->keys()->sort()->values()->all())->toBe(['novice:small', 'novice:small:heat-2']);
    expect($groups['novice:small'])->toHaveCount(8);
    expect($groups['novice:small']->where('is_npc', true))->toHaveCount(0);
    expect($groups['novice:small:heat-2'])->toHaveCount(8);
    expect($groups['novice:small:heat-2']->where('is_npc', true))->toHaveCount(7);
    expect($cancelled->fresh()->status)->toBe('cancelled');
    $this->travelTo($event->ends_at);

    $processor->processDue();

    $completed = $event->entries()->where('status', 'completed')->get();
    foreach ($completed->groupBy('division') as $heat) {
        expect($heat->pluck('rank')->sort()->values()->all())->toBe(range(1, 8));
        foreach ($heat->where('is_npc', false) as $entry) {
            $expectedPrize = $entry->result['eliminated'] ? 0 : ($event->rules['prizes'][$entry->rank - 1] ?? 0);
            expect($entry->prize)->toBe($expectedPrize);
            $this->assertDatabaseHas('users', ['id' => $entry->user_id, 'coins' => 475 + $expectedPrize]);
            expect($entry->pet->isBusy())->toBeFalse();
        }
    }
    expect($completed->where('is_npc', false))->toHaveCount(9);
    expect($completed->where('is_npc', true)->sum('prize'))->toBe(0);
    $transactionCount = CurrencyTransaction::query()->count();
    expect($processor->processDue())->toBe(0);
    expect(CurrencyTransaction::query()->count())->toBe($transactionCount);
    expect($event->fresh()->status)->toBe('settled');
});

test('heat capacity counts registered and frozen entries while keeping size classes separate', function () {
    $this->freezeSecond();
    $event = GameEvent::factory()->state(fn (array $attributes): array => [
        'rules' => [...$attributes['rules'], 'field_size' => 2],
    ])->create();
    foreach (['registered', 'frozen', 'cancelled', 'withdrawn'] as $status) {
        GameEventEntry::factory()->for($event, 'event')->create(['division' => 'novice:small', 'status' => $status]);
    }
    $small = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create(['size' => 'small']);
    $medium = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create(['size' => 'medium']);

    expect(registerDogInHeat($small, $event)->division)->toBe('novice:small:heat-2');
    expect(registerDogInHeat($medium, $event)->division)->toBe('novice:medium');
    $nextSmall = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create(['size' => 'small']);
    expect(registerDogInHeat($nextSmall, $event)->division)->toBe('novice:small:heat-2');
    $thirdHeat = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create(['size' => 'small']);
    expect(registerDogInHeat($thirdHeat, $event)->division)->toBe('novice:small:heat-3');
});

test('equal documentary results use a reproducible secret draw instead of token order', function (string $seed, int $winnerIndex) {
    $this->freezeSecond();
    $event = GameEvent::factory()->create(['discipline' => 'progeny', 'status' => 'frozen', 'seed' => $seed]);
    $snapshot = [
        'version' => 1, 'stats' => [], 'states' => [], 'skills' => [], 'modifiers' => [],
        'offspring' => array_fill(0, 3, ['exterior' => ['type' => 80, 'structure' => 80, 'movement' => 80], 'titles' => []]),
    ];
    $entries = collect();
    foreach (['00000000-0000-4000-8000-000000000001', 'ffffffff-ffff-4fff-8fff-ffffffffffff'] as $token) {
        $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create();
        $entries->push(GameEventEntry::factory()->for($event, 'event')->for($pet)->for($pet->user)->create([
            'operation_token' => $token, 'status' => 'frozen', 'snapshot' => $snapshot,
        ]));
    }
    $this->travelTo($event->ends_at);
    $processor = app(GameEventProcessor::class);

    $processor->processDue();

    expect($entries[0]->fresh()->result)->toBe($entries[1]->fresh()->result);
    expect($entries[$winnerIndex]->fresh()->rank)->toBe(1);
    expect($entries[1 - $winnerIndex]->fresh()->rank)->toBe(2);
    $ranks = $event->entries()->orderBy('id')->pluck('rank')->all();
    $balance = $entries[$winnerIndex]->user->fresh()->coins;
    expect($processor->processDue())->toBe(0);
    expect($event->entries()->orderBy('id')->pluck('rank')->all())->toBe($ranks);
    expect($entries[$winnerIndex]->user->fresh()->coins)->toBe($balance);
})->with(['higher token wins' => ['seed-a', 1], 'another seed changes the winner' => ['seed-b', 0]]);

test('heat labels preserve the class and localized breed name', function () {
    $breed = Dog::factory()->make(['name' => ['en' => 'Test breed', 'ru' => 'Тестовая порода']]);

    expect(GameEventDivisionData::base('novice:small:heat-12'))->toBe('novice:small');
    expect(GameEventDivisionData::heat('novice:small:heat-12'))->toBe(12);
    expect(GameEventDivisionData::heat('novice:small'))->toBe(1);
    expect(GameEventDivisionData::label('novice:small', 'en'))->toBe('Novice · Small dogs');
    expect(GameEventDivisionData::label('novice:small:heat-2', 'en'))->toBe('Novice · Small dogs · Heat 2');
    expect(GameEventDivisionData::label('open:breed-10:heat-2', 'ru', $breed))->toBe('Открытый класс · Тестовая порода · Группа 2');
});

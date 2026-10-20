<?php

use App\Models\Dog;
use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\PetTitle;
use App\Models\User;
use App\Modules\Pets\Actions\RegisterGameEvent;
use App\Modules\Pets\Actions\UpdateGameEventEntry;
use App\Modules\Pets\Exceptions\GameEventUnavailable;
use App\Modules\Pets\Queries\GetGameEvents;
use App\Modules\Pets\Queries\GetPetCareer;
use App\Modules\Pets\Services\GameEventAdmission;
use App\Modules\Pets\Services\GameEventProcessor;
use Illuminate\Support\Str;

test('exhibition entry uses the chosen class and age at the future show start', function () {
    $this->freezeSecond();
    $event = GameEvent::factory()->create(['discipline' => 'conformation']);
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create([
        'born_at' => $event->starts_at->subDays(6), 'sex' => 'female',
    ]);

    $dogs = app(GetGameEvents::class)->registrationDogs($pet->user, 'en', $event);
    $entry = app(RegisterGameEvent::class)->handle($pet->user, $event->id, $pet->id,
        ['stages' => ['careful', 'balanced', 'bold'], 'exhibition_class' => 'puppy'], [], 25, (string) Str::uuid());

    expect($dogs[0]['ageMonths'])->toBe(6);
    expect(array_column($dogs[0]['availableClasses'], 'key'))->toBe(['puppy']);
    expect($entry->plan['exhibition_class'])->toBe('puppy');
    expect($entry->division)->toBe('puppy:breed-'.$pet->dog_id.':female');
});

test('overlapping exhibition classes can be changed before closure but an expired class cannot be bought', function () {
    $this->freezeSecond();
    $event = GameEvent::factory()->create(['discipline' => 'conformation']);
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create(['born_at' => $event->starts_at->subDays(16)]);
    $entry = app(RegisterGameEvent::class)->handle($pet->user, $event->id, $pet->id,
        ['stages' => ['careful', 'balanced', 'bold'], 'exhibition_class' => 'junior'], [], 25, (string) Str::uuid());

    app(UpdateGameEventEntry::class)->handle($pet->user, $entry->id,
        ['stages' => ['careful', 'balanced', 'bold'], 'exhibition_class' => 'intermediate'], []);

    expect($entry->fresh()->division)->toStartWith('intermediate:');
    expect(fn () => app(UpdateGameEventEntry::class)->handle($pet->user, $entry->id,
        ['stages' => ['careful', 'balanced', 'bold'], 'exhibition_class' => 'baby'], []))
        ->toThrow(GameEventUnavailable::class, 'events.errors.exhibition_class');
    $this->assertDatabaseCount('currency_transactions', 1);
});

test('a show category keeps one podium above eight entrants and grants the breed titles once', function () {
    $this->freezeSecond();
    $event = GameEvent::factory()->create(['discipline' => 'conformation', 'seed' => 'age-show-one-ring']);
    $breed = Dog::factory()->create();
    foreach (range(1, 10) as $number) {
        $pet = Pet::factory()->for($breed)->for(User::factory()->state(['coins' => 500]))->create([
            'born_at' => $event->starts_at->subDays(30), 'sex' => 'male', 'exterior' => ['type' => 80, 'structure' => 80, 'movement' => 80],
        ]);
        app(RegisterGameEvent::class)->handle($pet->user, $event->id, $pet->id,
            ['stages' => ['careful', 'balanced', 'bold'], 'exhibition_class' => 'open'], [], 25, (string) Str::uuid());
    }
    $this->travelTo($event->closes_at);
    app(GameEventProcessor::class)->processDue();

    expect($event->entries()->where('is_npc', true)->count())->toBe(0);
    expect($event->entries()->distinct()->pluck('division')->all())->toBe(['open:breed-'.$breed->id.':male']);
    $this->travelTo($event->ends_at);
    app(GameEventProcessor::class)->processDue();

    $entries = $event->entries()->where('status', 'completed')->get();
    expect($entries->pluck('rank')->sort()->values()->all())->toBe(range(1, 10));
    $titles = PetTitle::query()->whereIn('game_event_entry_id', $entries->modelKeys())->get();
    expect($titles->where('code', 'conformation_class_place_1'))->toHaveCount(1);
    expect($titles->where('code', 'conformation_class_place_2'))->toHaveCount(1);
    expect($titles->where('code', 'conformation_class_place_3'))->toHaveCount(1);
    expect($titles->where('code', 'conformation_best_male'))->toHaveCount(1);
    expect($titles->where('code', 'conformation_best_of_breed'))->toHaveCount(1);
    expect($titles->where('code', 'conformation_best_female'))->toHaveCount(0);
    expect($titles->where('code', 'conformation_class_place_1')->first()->age_months)->toBe(30);
    expect($titles->pluck('class_key')->unique()->all())->toBe(['open']);
    app(GameEventProcessor::class)->processDue();
    expect(PetTitle::query()->whereIn('game_event_entry_id', $entries->modelKeys())->count())->toBe(5);
});

test('breed finals compare adult class winners across sexes and keep different breeds separate', function () {
    $this->freezeSecond();
    $event = GameEvent::factory()->create(['discipline' => 'conformation']);
    $event->update(['rules' => [...$event->rules, 'field_size' => 1]]);
    $breed = Dog::factory()->create();
    foreach (['intermediate' => 16, 'open' => 30, 'veteran' => 100] as $class => $age) {
        foreach (['male', 'female'] as $sex) {
            $pet = Pet::factory()->for($breed)->for(User::factory()->state(['coins' => 500]))->create([
                'born_at' => $event->starts_at->subDays($age), 'sex' => $sex,
            ]);
            app(RegisterGameEvent::class)->handle($pet->user, $event->id, $pet->id,
                ['stages' => ['careful', 'balanced', 'bold'], 'exhibition_class' => $class], [], 25, (string) Str::uuid());
        }
    }
    $other = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create(['born_at' => $event->starts_at->subDays(30), 'sex' => 'male']);
    app(RegisterGameEvent::class)->handle($other->user, $event->id, $other->id,
        ['stages' => ['careful', 'balanced', 'bold'], 'exhibition_class' => 'open'], [], 25, (string) Str::uuid());
    $this->travelTo($event->ends_at);

    app(GameEventProcessor::class)->processDue();

    $awards = PetTitle::query()->where('game_event_id', $event->id)->get();
    expect($awards->where('code', 'conformation_best_male'))->toHaveCount(2);
    expect($awards->where('code', 'conformation_best_female'))->toHaveCount(1);
    expect($awards->where('code', 'conformation_best_of_breed'))->toHaveCount(2);
    expect($awards->where('breed_id', $breed->id))->toHaveCount(9);
    foreach ($awards->where('code', 'conformation_best_of_breed') as $best) {
        expect($awards->where('game_event_entry_id', $best->game_event_entry_id)->pluck('code')->all())
            ->toContain('conformation_best_'.$best->sex, 'conformation_class_place_1');
    }
});

test('event age limits use the exact start age for sport and every exhibition', function (string $discipline, int $months, ?string $expected) {
    $this->freezeSecond();
    $event = GameEvent::factory()->create(['discipline' => $discipline]);
    $pet = Pet::factory()->create(['born_at' => $event->starts_at->subDays($months)]);

    expect(app(GameEventAdmission::class)->reason($event, $pet, now()))->toBe($expected);
})->with([
    'sport too young' => ['agility', 17, 'events.errors.young'],
    'sport adulthood' => ['agility', 18, null],
    'sport last year' => ['agility', 95, null],
    'sport senior' => ['agility', 96, null],
    'show too young' => ['conformation', 2, 'events.errors.young'],
    'baby begins' => ['conformation', 3, null],
    'veteran last month' => ['conformation', 119, null],
    'senior show career remains open' => ['conformation', 120, null],
    'senior documentary career remains open' => ['progeny', 120, null],
]);

test('career keeps podium medals in different age classes after the dog grows up', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['born_at' => now()->subDays(10)]);
    foreach (['baby' => 6, 'puppy' => 2] as $class => $daysAgo) {
        $awardedAt = now()->subDays($daysAgo);
        $event = GameEvent::factory()->create([
            'discipline' => 'conformation', 'status' => 'settled', 'registration_opens_at' => $awardedAt->subDay(),
            'closes_at' => $awardedAt->subMinutes(25), 'starts_at' => $awardedAt->subMinutes(10), 'ends_at' => $awardedAt,
        ]);
        $entry = GameEventEntry::factory()->for($event, 'event')->for($pet)->for($pet->user)->create([
            'status' => 'completed', 'completed_at' => $awardedAt, 'rank' => 2, 'result' => ['eliminated' => false],
        ]);
        PetTitle::factory()->for($pet)->for($entry, 'entry')->create([
            'game_event_id' => $event->id, 'discipline' => 'conformation', 'frequency' => 'daily',
            'code' => 'conformation_class_place_2', 'class_key' => $class, 'rank' => 2, 'awarded_at' => $awardedAt,
        ]);
    }

    $career = app(GetPetCareer::class)->handle($pet->user, $pet->id, 'en');

    expect($career['titles'])->toHaveCount(2);
    expect(array_column($career['titles'], 'classKey'))->toBe(['puppy', 'baby']);
    expect(array_column($career['titles'], 'count'))->toBe([1, 1]);
});

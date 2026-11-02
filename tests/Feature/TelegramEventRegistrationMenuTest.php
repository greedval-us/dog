<?php

use App\Http\Telegram\Menus\EventRegistrationMenu;
use App\Models\BreedingLitter;
use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\InventoryItem;
use App\Models\Pet;
use App\Models\PetSportRecord;
use App\Models\Puppy;
use App\Models\User;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Players\Enums\PlayerStatus;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

function telegramRegistrationEvent(string $discipline = 'agility', array $stages = ['opening', 'technical', 'finish'], array $regulation = []): GameEvent
{
    return GameEvent::factory()->create(['discipline' => $discipline, 'rules' => [
        'version' => 3, 'fee' => 25, 'prizes' => [100, 60, 40], 'energy_cost' => 10,
        'field_size' => 8, 'time_limit' => 360, 'stages' => $stages,
        'regulation' => ['version' => 2, 'system' => 'rkf', 'rank' => 'qualifying', 'format' => $discipline, ...$regulation],
    ]]);
}

function telegramRegistrationGear(User $owner, string $slot, string $phase = 'preparation', array $attributes = []): InventoryItem
{
    return InventoryItem::factory()->for($owner)->create(['characteristics' => ['competition' => [
        'slot' => $slot, 'phase' => $phase, 'disciplines' => ['agility', 'canicross'],
        'sizes' => ['small', 'medium', 'large'], 'modifiers' => ['focus' => 0.05],
        'description' => ['en' => 'Preparation', 'ru' => 'Подготовка'],
    ]], ...$attributes]);
}

test('telegram event dog selection pages all own dogs and refuses a foreign dog', function () {
    $this->freezeSecond();
    $owner = User::factory()->create(['status' => PlayerStatus::Active]);
    $pets = Pet::factory()->for($owner)->count(7)->create();
    $foreign = Pet::factory()->create();
    $event = GameEvent::factory()->create();
    $menu = app(EventRegistrationMenu::class);

    $first = $menu->prepare($owner, 'en', ['eventId' => $event->id]);
    $second = $menu->prepare($owner, 'en', ['eventId' => $event->id, 'offset' => 6]);

    expect(collect($first->rows)->flatten(1)->pluck('command')->whereNotNull('petId'))->toHaveCount(6);
    expect(collect($second->rows)->flatten(1)->pluck('command')->firstWhere('petId', $pets->last()->id))->not->toBeNull();
    expect(fn () => $menu->prepare($owner, 'en', ['eventId' => $event->id, 'petId' => $foreign->id]))->toThrow(ModelNotFoundException::class);
});

test('telegram event drafts use each event stage count and preserve the quote on navigation', function (string $discipline, array $stages, array $regulation) {
    $this->freezeSecond();
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500, 'status' => PlayerStatus::Active]))->create();
    $event = telegramRegistrationEvent($discipline, $stages, $regulation);
    if ($discipline === 'nosework') {
        PetSportRecord::factory()->for($pet)->create(['discipline' => 'nosework', 'regulatory_progress' => [
            'nosework_tests' => ['pr1' => ['birch', 'lavender', 'anise'], 'pr2' => ['cinnamon', 'orange', 'clove']],
        ]]);
    }
    $menu = app(EventRegistrationMenu::class);

    $screen = $menu->prepare($pet->user, 'en', ['eventId' => $event->id, 'petId' => $pet->id]);
    $review = $menu->draft($pet->user, 'en', ['action' => 'events.draft.show', 'step' => 'review'], $screen->state);

    expect($screen->state['eventDraft']['plan']['stages'])->toBe(array_fill(0, count($stages), 'balanced'));
    expect(Str::isUuid($screen->state['eventDraft']['token']))->toBeTrue();
    expect($screen->rows[array_key_last($screen->rows)][0]['command'])->toBe(['action' => 'events.prepare', 'eventId' => $event->id]);
    expect($review->state)->toBe($screen->state);
    expect($review->text)->toContain('25', 'Registration closes');
})->with([
    'odor recognition' => ['nosework', ['odor_test'], ['format' => 'odor_test']],
    'ordinary agility' => ['agility', ['opening', 'technical', 'finish'], []],
    'four search categories' => ['nosework', ['interior', 'exterior', 'objects', 'transport'], ['rank' => 'certificate']],
    'combined agility' => ['agility', ['agility_opening', 'agility_technical', 'agility_finish', 'jumping_opening', 'jumping_technical', 'jumping_finish'], ['format' => 'agility_combined']],
]);

test('telegram event class changes retain chosen stage decisions and reject unavailable options', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500, 'status' => PlayerStatus::Active]))->create();
    $event = telegramRegistrationEvent();
    $menu = app(EventRegistrationMenu::class);
    $start = $menu->prepare($pet->user, 'en', ['eventId' => $event->id, 'petId' => $pet->id]);
    $chosen = $menu->draft($pet->user, 'en', ['action' => 'events.draft.stage', 'stageIndex' => 1, 'decision' => 'bold'], $start->state);

    $sameClass = $menu->draft($pet->user, 'en', ['action' => 'events.draft.option', 'optionKey' => 'agility_1'], $chosen->state);

    expect($sameClass->state['eventDraft']['plan'])->toBe(['stages' => ['balanced', 'bold', 'balanced'], 'sport_class' => 'agility_1']);
    expect($chosen->state['eventDraft']['token'])->not->toBe($start->state['eventDraft']['token']);
    expect($sameClass->state['eventDraft']['token'])->toBe($chosen->state['eventDraft']['token']);
    expect(fn () => $menu->draft($pet->user, 'en', ['action' => 'events.draft.option', 'optionKey' => 'agility_3'], $sameClass->state))->toThrow(PetUnavailable::class);
});

test('telegram historical conformation offers the dogs age classes without regulated options', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create();
    $event = GameEvent::factory()->create(['discipline' => 'conformation']);
    $menu = app(EventRegistrationMenu::class);
    $start = $menu->prepare($pet->user, 'en', ['eventId' => $event->id, 'petId' => $pet->id]);

    $selected = $menu->draft($pet->user, 'en', ['action' => 'events.draft.option', 'optionKey' => 'open'], $start->state);

    expect($selected->state['eventDraft']['plan'])->toBe(['stages' => ['balanced', 'balanced', 'balanced'], 'exhibition_class' => 'open']);
});

test('telegram event equipment filters discipline size and phase and replaces only its own slot', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500, 'status' => PlayerStatus::Active]))->create();
    $event = telegramRegistrationEvent();
    $first = telegramRegistrationGear($pet->user, 'preparation');
    $second = telegramRegistrationGear($pet->user, 'preparation');
    $invalid = telegramRegistrationGear($pet->user, 'body', 'performance');
    $foreign = telegramRegistrationGear(User::factory()->create(), 'preparation');
    $menu = app(EventRegistrationMenu::class);
    $start = $menu->prepare($pet->user, 'en', ['eventId' => $event->id, 'petId' => $pet->id]);
    $gear = $menu->draft($pet->user, 'en', ['action' => 'events.draft.show', 'step' => 'gear'], $start->state);
    $one = $menu->draft($pet->user, 'en', ['action' => 'events.draft.gear', 'gearId' => $first->id], $gear->state);

    $two = $menu->draft($pet->user, 'en', ['action' => 'events.draft.gear', 'gearId' => $second->id], $one->state);

    expect(collect($gear->rows)->flatten(1)->pluck('command')->where('action', 'events.draft.gear')->pluck('gearId')->all())->toBe([$first->id, $second->id]);
    expect($two->state['eventDraft']['gearIds'])->toBe([$second->id]);
    expect(fn () => $menu->draft($pet->user, 'en', ['action' => 'events.draft.gear', 'gearId' => $invalid->id], $two->state))->toThrow(PetUnavailable::class);
    expect(fn () => $menu->draft($pet->user, 'en', ['action' => 'events.draft.gear', 'gearId' => $foreign->id], $two->state))->toThrow(PetUnavailable::class);
    $this->assertDatabaseCount('item_usages', 0);
    $this->assertDatabaseCount('game_event_entries', 0);
});

test('telegram event editing preserves raw plan and gear order until an explicit change', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create();
    $event = telegramRegistrationEvent();
    $gear = telegramRegistrationGear($pet->user, 'preparation');
    $plan = ['sport_class' => 'agility_1', 'stages' => ['bold', 'careful', 'balanced']];
    $entry = GameEventEntry::factory()->for($event, 'event')->for($pet)->for($pet->user)->create(['plan' => $plan, 'gear_ids' => [$gear->id]]);
    $menu = app(EventRegistrationMenu::class);

    $start = $menu->prepare($pet->user, 'en', ['eventId' => $event->id, 'entryId' => $entry->id]);
    $review = $menu->draft($pet->user, 'en', ['action' => 'events.draft.show', 'step' => 'review'], $start->state);

    expect($review->state['eventDraft']['plan'])->toBe($plan);
    expect($review->state['eventDraft']['gearIds'])->toBe([$gear->id]);
    expect($review->state['eventDraft']['entryId'])->toBe($entry->id);
    expect($review->state['eventDraft']['token'])->toBe($start->state['eventDraft']['token']);
    expect(collect($review->rows)->flatten(1)->pluck('command')->firstWhere('action', 'events.update'))->not->toBeNull();
    $selectedDog = $menu->prepare($pet->user, 'en', ['eventId' => $event->id, 'petId' => $pet->id]);
    expect($selectedDog->state['eventDraft']['entryId'])->toBe($entry->id);
    expect($selectedDog->state['eventDraft']['plan'])->toBe($plan);
});

test('telegram dog selection shows an existing closed receipt without offering registration again', function (string $status) {
    $this->freezeSecond();
    $pet = Pet::factory()->create();
    $event = telegramRegistrationEvent('conformation');
    $entry = GameEventEntry::factory()->for($event, 'event')->for($pet)->for($pet->user)->create(['status' => $status]);

    $screen = app(EventRegistrationMenu::class)->prepare($pet->user, 'en', ['eventId' => $event->id, 'petId' => $pet->id]);

    expect($screen->state)->not->toHaveKey('eventDraft');
    expect(collect($screen->rows)->flatten(1)->pluck('command')->firstWhere('action', 'events.entry'))->toBe(['action' => 'events.entry', 'eventId' => $event->id, 'entryId' => $entry->id]);
    expect(collect($screen->rows)->flatten(1)->pluck('command')->firstWhere('action', 'events.register'))->toBeNull();
})->with(['cancelled', 'withdrawn']);

test('telegram event drafts let players remove equipment that became unavailable after selection', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create();
    $event = telegramRegistrationEvent();
    $gear = telegramRegistrationGear($pet->user, 'preparation');
    $menu = app(EventRegistrationMenu::class);
    $start = $menu->prepare($pet->user, 'en', ['eventId' => $event->id, 'petId' => $pet->id]);
    $selected = $menu->draft($pet->user, 'en', ['action' => 'events.draft.gear', 'gearId' => $gear->id], $start->state);
    $gear->delete();
    $screen = $menu->draft($pet->user, 'en', ['action' => 'events.draft.show', 'step' => 'gear'], $selected->state);

    $removed = $menu->draft($pet->user, 'en', ['action' => 'events.draft.gear', 'gearId' => $gear->id], $screen->state);

    expect(collect($screen->rows)->flatten(1)->pluck('command')->firstWhere('gearId', $gear->id))->not->toBeNull();
    expect($removed->state['eventDraft']['gearIds'])->toBe([]);
    expect($screen->state['eventDraft']['token'])->toBe($selected->state['eventDraft']['token']);
});

test('telegram historical progeny allows selecting offspring and hides tactics and equipment', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500, 'status' => PlayerStatus::Active]))->create();
    $children = Pet::factory()->for($pet->dog)->count(3)->create(['father_id' => $pet->id]);
    $event = GameEvent::factory()->create(['discipline' => 'progeny']);
    $menu = app(EventRegistrationMenu::class);
    $screen = $menu->prepare($pet->user, 'en', ['eventId' => $event->id, 'petId' => $pet->id]);
    foreach ($children as $child) {
        $screen = $menu->draft($pet->user, 'en', ['action' => 'events.draft.member', 'memberId' => $child->id], $screen->state);
    }

    $review = $menu->draft($pet->user, 'en', ['action' => 'events.draft.show', 'step' => 'review'], $screen->state);

    expect($review->state['eventDraft']['plan']['offspring_ids'])->toBe($children->modelKeys());
    expect($review->state['eventDraft']['plan']['stages'])->toBe(['balanced', 'balanced', 'balanced']);
    expect($review->state['eventDraft']['gearIds'])->toBe([]);
    expect(collect($review->rows)->flatten(1)->pluck('command')->whereIn('step', ['stages', 'gear']))->toBeEmpty();
    expect(collect($review->rows)->flatten(1)->pluck('command')->firstWhere('action', 'events.register'))->not->toBeNull();
});

test('telegram linked couple competition includes an eligible partner and fixes group tactics', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500, 'status' => PlayerStatus::Active]))->create([
        'event_profile' => ['recognized_rkf' => true, 'registered' => true],
    ]);
    $mate = Pet::factory()->for($pet->user)->for($pet->dog)->female()->create(['name' => '<Partner>']);
    $show = telegramRegistrationEvent('conformation');
    foreach ([$pet, $mate] as $member) {
        GameEventEntry::factory()->for($show, 'event')->for($member)->for($member->user)->create(['plan' => ['exhibition_class' => 'open', 'stages' => ['balanced', 'balanced', 'balanced']]]);
    }
    $event = telegramRegistrationEvent('couple', ['type', 'uniformity', 'movement'], ['format' => 'couple_ring']);
    $event->update(['starts_at' => $show->ends_at->addMinutes(5), 'ends_at' => $show->ends_at->addMinutes(15), 'rules' => [...$event->rules, 'linked_show_id' => $show->id]]);
    $menu = app(EventRegistrationMenu::class);
    $screen = $menu->prepare($pet->user, 'en', ['eventId' => $event->id, 'petId' => $pet->id]);

    $selected = $menu->draft($pet->user, 'en', ['action' => 'events.draft.member', 'memberId' => $mate->id], $screen->state);

    expect($screen->text)->toContain('&lt;Partner&gt;');
    expect($selected->state['eventDraft']['plan'])->toBe(['stages' => ['balanced', 'balanced', 'balanced'], 'member_ids' => [$mate->id]]);
    expect(fn () => $menu->draft($pet->user, 'en', ['action' => 'events.draft.stage', 'stageIndex' => 0, 'decision' => 'bold'], $selected->state))->toThrow(PetUnavailable::class);
});

test('telegram linked breeders groups include qualified dogs from another owner with the same kennel origin', function () {
    $this->freezeSecond();
    $owner = User::factory()->create(['coins' => 500, 'status' => PlayerStatus::Active]);
    $litter = BreedingLitter::factory()->create(['delivered_at' => now(), 'snapshots' => ['breeding_origin' => [
        'breeder_key' => 'player:42', 'kennel_key' => 'player:42', 'kennel_name' => 'DogLive #42',
    ]]]);
    $pet = Pet::factory()->for($owner)->for($litter->father->dog)->create([
        'father_id' => $litter->father_id, 'mother_id' => $litter->mother_id,
        'event_profile' => ['recognized_rkf' => true, 'registered' => true],
    ]);
    $members = Pet::factory()->for($pet->dog)->count(2)->create(['father_id' => $litter->father_id, 'mother_id' => $litter->mother_id]);
    $show = telegramRegistrationEvent('conformation');
    foreach ([$pet, ...$members] as $member) {
        Puppy::factory()->for($litter, 'litter')->create(['pet_id' => $member->id, 'status' => 'placed']);
        GameEventEntry::factory()->for($show, 'event')->for($member)->for($member->user)->create(['plan' => ['exhibition_class' => 'open', 'stages' => ['balanced', 'balanced', 'balanced']]]);
    }
    $event = telegramRegistrationEvent('breeders', ['type', 'uniformity', 'movement'], ['format' => 'breeders_ring']);
    $event->update(['starts_at' => $show->ends_at->addMinutes(5), 'ends_at' => $show->ends_at->addMinutes(15), 'rules' => [...$event->rules, 'linked_show_id' => $show->id]]);
    $menu = app(EventRegistrationMenu::class);
    $screen = $menu->prepare($owner, 'en', ['eventId' => $event->id, 'petId' => $pet->id]);
    foreach ($members as $member) {
        $screen = $menu->draft($owner, 'en', ['action' => 'events.draft.member', 'memberId' => $member->id], $screen->state);
    }

    $review = $menu->draft($owner, 'en', ['action' => 'events.draft.show', 'step' => 'review'], $screen->state);

    expect($screen->text)->toContain($members->first()->user->username);
    expect($review->state['eventDraft']['plan']['member_ids'])->toBe($members->modelKeys());
    expect(collect($review->rows)->flatten(1)->pluck('command')->firstWhere('action', 'events.register'))->not->toBeNull();
});

test('telegram event canicross review requires all three performance kit slots', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500, 'status' => PlayerStatus::Active]))->create();
    $event = telegramRegistrationEvent('canicross', ['start', 'course', 'finish']);
    $gear = collect(['body', 'line', 'handler'])->map(fn (string $slot): InventoryItem => telegramRegistrationGear($pet->user, $slot, 'performance'));
    $menu = app(EventRegistrationMenu::class);
    $screen = $menu->prepare($pet->user, 'en', ['eventId' => $event->id, 'petId' => $pet->id]);
    $missing = $menu->draft($pet->user, 'en', ['action' => 'events.draft.show', 'step' => 'review'], $screen->state);
    foreach ($gear as $item) {
        $screen = $menu->draft($pet->user, 'en', ['action' => 'events.draft.gear', 'gearId' => $item->id], $screen->state);
    }

    $ready = $menu->draft($pet->user, 'en', ['action' => 'events.draft.show', 'step' => 'review'], $screen->state);

    expect(collect($missing->rows)->flatten(1)->pluck('command')->firstWhere('action', 'events.register'))->toBeNull();
    expect(collect($ready->rows)->flatten(1)->pluck('command')->firstWhere('action', 'events.register'))->not->toBeNull();
    expect($ready->state['eventDraft']['gearIds'])->toBe($gear->pluck('id')->all());
});

test('telegram closed registration and unavailable dogs never offer a commit', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->retired()->create();
    $event = GameEvent::factory()->create();
    $menu = app(EventRegistrationMenu::class);
    $blocked = $menu->prepare($pet->user, 'en', ['eventId' => $event->id, 'petId' => $pet->id]);
    $event->update(['closes_at' => now()]);

    $closed = $menu->prepare($pet->user, 'en', ['eventId' => $event->id]);

    expect($blocked->state)->not->toHaveKey('eventDraft');
    expect(collect($blocked->rows)->flatten(1)->pluck('command')->whereIn('action', ['events.register', 'events.update']))->toBeEmpty();
    expect($closed->text)->toContain('Registration is closed');
    $this->assertDatabaseCount('game_event_entries', 0);
});

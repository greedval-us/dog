<?php

use App\Http\Telegram\Menus\EventEntryMenu;
use App\Http\Telegram\Menus\SavedEventDraft;
use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\InventoryItem;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Actions\RegisterGameEvent;
use App\Modules\Pets\Exceptions\GameEventUnavailable;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Players\Enums\PlayerStatus;
use App\Modules\Telegram\DTO\TelegramScreen;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

/** @return list<array<string, mixed>> */
function telegramEventEntryCommands(TelegramScreen $screen, string $action): array
{
    return collect($screen->rows)->flatten(1)->pluck('command')->filter()->where('action', $action)->values()->all();
}

/** @return array{eventDraft:array<string, mixed>} */
function telegramEventEntryDraft(Pet $pet, GameEvent $event, ?GameEventEntry $entry = null): array
{
    return ['eventDraft' => [
        'userId' => $pet->user_id, 'eventId' => $event->id, 'petId' => $pet->id, 'entryId' => $entry?->id,
        'fee' => 25, 'token' => (string) Str::uuid(), 'plan' => ['stages' => ['balanced', 'careful', 'bold']], 'gearIds' => [],
    ]];
}

test('telegram saved event drafts preserve the raw plan gear order and retry token including incomplete groups', function () {
    $user = User::factory()->make(['id' => 4]);
    $values = ['userId' => 4, 'eventId' => 6, 'petId' => 8, 'entryId' => null, 'fee' => 25,
        'token' => 'ABCDEF12-1234-4123-8123-123456789ABC',
        'plan' => ['member_ids' => [19], 'handler_category' => 'adult', 'stages' => ['balanced'], 'offspring_ids' => []], 'gearIds' => [12, 7]];

    $draft = new SavedEventDraft($user, ['eventDraft' => $values]);

    expect($draft->toArray())->toBe($values);
    expect($draft->plan)->toBe($values['plan']);
    expect($draft->gearIds)->toBe([12, 7]);
    expect($draft->token)->toBe('ABCDEF12-1234-4123-8123-123456789ABC');
});

test('telegram event confirmations reject malformed saved drafts before gameplay', function (array $changes, bool $removeDraft) {
    $user = User::factory()->create();
    $values = ['userId' => $user->id, 'eventId' => 1, 'petId' => 1, 'entryId' => null, 'fee' => 25,
        'token' => '00000000-0000-4000-8000-000000000000', 'plan' => ['stages' => ['balanced']], 'gearIds' => []];
    $state = $removeDraft ? [] : ['eventDraft' => array_replace($values, $changes)];

    expect(fn () => app(EventEntryMenu::class)->register($user, 'en', $state))
        ->toThrow(PetUnavailable::class, 'telegram.errors.stale');

    $this->assertDatabaseCount('game_event_entries', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
})->with([
    'missing draft' => [[], true],
    'foreign owner' => [['userId' => PHP_INT_MAX], false],
    'non-scalar event' => [['eventId' => []], false],
    'missing pet' => [['petId' => 0], false],
    'invalid entry' => [['entryId' => '1'], false],
    'registration has an existing entry' => [['entryId' => 1], false],
    'zero fee' => [['fee' => 0], false],
    'invalid token' => [['token' => 'invalid'], false],
    'unknown plan key' => [['plan' => ['stages' => ['balanced'], 'fee' => 0]], false],
    'empty stages' => [['plan' => ['stages' => []]], false],
    'too many stages' => [['plan' => ['stages' => array_fill(0, 7, 'balanced')]], false],
    'non-list stages' => [['plan' => ['stages' => [1 => 'balanced']]], false],
    'invalid decision' => [['plan' => ['stages' => ['unknown']]], false],
    'nested class' => [['plan' => ['stages' => ['balanced'], 'sport_class' => []]], false],
    'invalid exhibition class' => [['plan' => ['stages' => ['balanced'], 'exhibition_class' => 'invalid']], false],
    'invalid program' => [['plan' => ['stages' => ['balanced'], 'program' => 'invalid']], false],
    'invalid odor' => [['plan' => ['stages' => ['balanced'], 'test_type' => 'invalid']], false],
    'duplicate members' => [['plan' => ['stages' => ['balanced'], 'member_ids' => [1, 1]]], false],
    'nested offspring' => [['plan' => ['stages' => ['balanced'], 'offspring_ids' => [[]]]], false],
    'non-list gear' => [['gearIds' => [1 => 3]], false],
    'duplicate gear' => [['gearIds' => [1, 1]], false],
    'too much gear' => [['gearIds' => [1, 2, 3, 4, 5]], false],
]);

test('telegram event registration forwards the exact raw payload and retry token and charges once', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create(['name' => '<Rex>']);
    $event = GameEvent::factory()->create();
    $gear = [];
    foreach (['body', 'preparation'] as $slot) {
        $gear[] = InventoryItem::factory()->for($pet->user)->create(['remaining_uses' => 2, 'characteristics' => ['competition' => [
            'slot' => $slot, 'phase' => 'preparation', 'disciplines' => ['agility'], 'sizes' => [$pet->size->value],
            'modifiers' => ['focus' => 0.1], 'description' => ['ru' => 'Подготовка', 'en' => 'Preparation'],
        ]]])->id;
    }
    $state = telegramEventEntryDraft($pet, $event);
    $state['eventDraft']['plan'] = ['sport_class' => 'open', 'stages' => ['balanced', 'careful', 'bold']];
    $state['eventDraft']['gearIds'] = array_reverse($gear);
    $state['eventDraft']['token'] = strtoupper($state['eventDraft']['token']);
    $menu = app(EventEntryMenu::class);

    $screen = $menu->register($pet->user, 'en', $state);
    $menu->register($pet->user, 'en', $state);
    $entry = GameEventEntry::query()->sole();

    expect($entry->registration_hash)->toBe(hash('sha256', serialize([$event->id, $pet->id, 25,
        ['sport_class' => 'open', 'stages' => ['balanced', 'careful', 'bold']], array_reverse($gear)])));
    expect($entry->operation_token)->toBe(strtolower($state['eventDraft']['token']));
    expect($entry->gear_ids)->toBe(array_reverse($gear));
    expect($screen->text)->toContain('&lt;Rex&gt;')->not->toContain('<Rex>');
    expect(telegramEventEntryCommands($screen, 'events.entry'))->toContain(['action' => 'events.entry', 'eventId' => $event->id, 'entryId' => $entry->id]);
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'coins' => 475]);
    $this->assertDatabaseCount('currency_transactions', 1);
    $this->assertDatabaseCount('game_event_entries', 1);
    $this->assertDatabaseHas('inventory_items', ['id' => $gear[0], 'remaining_uses' => 2]);
});

test('telegram event updates change the owned receipt without charging another registration fee', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create(['name' => 'Rex']);
    $event = GameEvent::factory()->create();
    $entry = app(RegisterGameEvent::class)->handle($pet->user, $event->id, $pet->id, ['stages' => ['balanced', 'balanced', 'balanced']], [], 25, (string) Str::uuid());
    $state = telegramEventEntryDraft($pet, $event, $entry);
    $state['eventDraft']['plan']['stages'] = ['bold', 'careful', 'careful'];

    $screen = app(EventEntryMenu::class)->update($pet->user, 'en', $state);

    expect($entry->fresh()->plan)->toBe(['stages' => ['bold', 'careful', 'careful']]);
    expect(telegramEventEntryCommands($screen, 'events.entry'))->toContain(['action' => 'events.entry', 'eventId' => $event->id, 'entryId' => $entry->id]);
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'coins' => 475]);
    $this->assertDatabaseCount('currency_transactions', 1);
});

test('telegram event updates require a saved receipt before gameplay', function () {
    $user = User::factory()->create();
    $state = ['eventDraft' => ['userId' => $user->id, 'eventId' => 1, 'petId' => 1, 'entryId' => null, 'fee' => 25,
        'token' => '00000000-0000-4000-8000-000000000000', 'plan' => ['stages' => ['balanced']], 'gearIds' => []]];

    expect(fn () => app(EventEntryMenu::class)->update($user, 'en', $state))->toThrow(PetUnavailable::class, 'telegram.errors.stale');

    $this->assertDatabaseCount('game_event_entries', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('telegram event registration lets native admission reject changed conditions without taking coins', function (string $condition, string $reason) {
    $this->freezeSecond();
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create();
    $event = GameEvent::factory()->create();
    $state = telegramEventEntryDraft($pet, $event);
    match ($condition) {
        'fee' => $event->update(['rules' => [...$event->rules, 'fee' => 30]]),
        'closed' => $this->travelTo($event->closes_at),
        'blocked' => $pet->user->forceFill(['status' => PlayerStatus::Blocked])->save(),
    };

    expect(fn () => app(EventEntryMenu::class)->register($pet->user, 'en', $state))->toThrow(GameEventUnavailable::class, $reason);

    $this->assertDatabaseCount('game_event_entries', 0);
    $this->assertDatabaseCount('currency_transactions', 0);
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'coins' => 500]);
})->with([
    'fee changed' => ['fee', 'events.errors.fee'],
    'deadline reached' => ['closed', 'events.errors.closed'],
    'player blocked' => ['blocked', 'events.errors.blocked'],
]);

test('telegram event cancellation confirms the receipt refund and cancels it only once', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create(['name' => '<Rex>']);
    $event = GameEvent::factory()->create();
    $entry = app(RegisterGameEvent::class)->handle($pet->user, $event->id, $pet->id, ['stages' => ['balanced', 'balanced', 'balanced']], [], 25, (string) Str::uuid());
    $menu = app(EventEntryMenu::class);

    $confirmation = $menu->cancelConfirmation($pet->user, 'en', ['eventId' => $event->id, 'entryId' => $entry->id]);

    expect($confirmation->state)->toBe(['eventCancel' => ['userId' => $pet->user_id, 'eventId' => $event->id, 'entryId' => $entry->id, 'petId' => $pet->id]]);
    expect($confirmation->text)->toContain('&lt;Rex&gt;', '25', $event->closes_at->setTimezone($pet->user->timezone)->format('d.m H:i T'))->not->toContain('<Rex>');
    expect(telegramEventEntryCommands($confirmation, 'events.cancel'))->toBe([['action' => 'events.cancel']]);
    $this->assertDatabaseHas('game_event_entries', ['id' => $entry->id, 'status' => 'registered']);
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'coins' => 475]);

    $screen = $menu->cancel($pet->user, 'en', $confirmation->state);
    $menu->cancel($pet->user, 'en', $confirmation->state);

    expect(telegramEventEntryCommands($screen, 'events.entry'))->toContain(['action' => 'events.entry', 'eventId' => $event->id, 'entryId' => $entry->id]);
    $this->assertDatabaseHas('game_event_entries', ['id' => $entry->id, 'status' => 'cancelled']);
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'coins' => 500]);
    $this->assertDatabaseCount('currency_transactions', 2);
});

test('telegram cancellation rejects a confirmation after its native deadline without refunding', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->for(User::factory()->state(['coins' => 500]))->create();
    $event = GameEvent::factory()->create();
    $entry = app(RegisterGameEvent::class)->handle($pet->user, $event->id, $pet->id, ['stages' => ['balanced', 'balanced', 'balanced']], [], 25, (string) Str::uuid());
    $menu = app(EventEntryMenu::class);
    $confirmation = $menu->cancelConfirmation($pet->user, 'en', ['eventId' => $event->id, 'entryId' => $entry->id]);
    $this->travelTo($event->closes_at);

    expect(fn () => $menu->cancel($pet->user, 'en', $confirmation->state))->toThrow(GameEventUnavailable::class, 'events.errors.closed');

    $this->assertDatabaseHas('game_event_entries', ['id' => $entry->id, 'status' => 'registered']);
    $this->assertDatabaseHas('users', ['id' => $pet->user_id, 'coins' => 475]);
    $this->assertDatabaseCount('currency_transactions', 1);
});

test('telegram event mutations reject foreign receipts and mismatched event or dog confirmations', function () {
    $this->freezeSecond();
    $entry = GameEventEntry::factory()->create();
    $user = User::factory()->create();
    $otherPet = Pet::factory()->for($entry->user)->create();
    $otherEvent = GameEvent::factory()->create(['discipline' => 'nosework']);
    $menu = app(EventEntryMenu::class);
    $foreign = ['eventCancel' => ['userId' => $user->id, 'eventId' => $entry->game_event_id, 'entryId' => $entry->id, 'petId' => $entry->pet_id]];

    expect(fn () => $menu->cancelConfirmation($user, 'en', ['eventId' => $entry->game_event_id, 'entryId' => $entry->id]))->toThrow(ModelNotFoundException::class);
    expect(fn () => $menu->cancel($user, 'en', $foreign))->toThrow(ModelNotFoundException::class);
    $foreignUpdate = telegramEventEntryDraft($entry->pet, $entry->event, $entry);
    $foreignUpdate['eventDraft']['userId'] = $user->id;
    expect(fn () => $menu->update($user, 'en', $foreignUpdate))->toThrow(ModelNotFoundException::class);
    expect(fn () => $menu->cancelConfirmation($entry->user, 'en', ['eventId' => $otherEvent->id, 'entryId' => $entry->id]))->toThrow(ModelNotFoundException::class);
    $wrongDog = telegramEventEntryDraft($otherPet, $entry->event, $entry);
    expect(fn () => $menu->update($entry->user, 'en', $wrongDog))->toThrow(ModelNotFoundException::class);
    $wrongEvent = telegramEventEntryDraft($entry->pet, $otherEvent, $entry);
    expect(fn () => $menu->update($entry->user, 'en', $wrongEvent))->toThrow(ModelNotFoundException::class);
    $cancel = ['eventCancel' => ['userId' => $entry->user_id, 'eventId' => $entry->game_event_id, 'entryId' => $entry->id, 'petId' => $otherPet->id]];
    expect(fn () => $menu->cancel($entry->user, 'en', $cancel))->toThrow(ModelNotFoundException::class);
    $entry->pet->update(['user_id' => $user->id]);
    $cancel['eventCancel']['petId'] = $entry->pet_id;
    expect(fn () => $menu->cancelConfirmation($entry->user, 'en', ['eventId' => $entry->game_event_id, 'entryId' => $entry->id]))->toThrow(ModelNotFoundException::class);
    expect(fn () => $menu->cancel($entry->user, 'en', $cancel))->toThrow(ModelNotFoundException::class);

    $this->assertDatabaseHas('game_event_entries', ['id' => $entry->id, 'status' => 'registered']);
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('telegram cancellation rejects missing saved identifiers and owner changes before gameplay', function (array $state) {
    $user = User::factory()->create();

    expect(fn () => app(EventEntryMenu::class)->cancel($user, 'en', $state))->toThrow(PetUnavailable::class, 'telegram.errors.stale');

    $this->assertDatabaseCount('currency_transactions', 0);
})->with([
    'missing confirmation' => [[]],
    'wrong owner' => [['eventCancel' => ['userId' => PHP_INT_MAX, 'eventId' => 1, 'entryId' => 1, 'petId' => 1]]],
    'invalid event' => [['eventCancel' => ['userId' => 1, 'eventId' => [], 'entryId' => 1, 'petId' => 1]]],
    'invalid dog' => [['eventCancel' => ['userId' => 1, 'eventId' => 1, 'entryId' => 1, 'petId' => null]]],
]);

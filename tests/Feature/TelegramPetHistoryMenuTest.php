<?php

use App\Http\Telegram\Menus\PetHistoryMenu;
use App\Models\Pet;
use App\Models\PetHistoryEntry;
use App\Models\PetHistoryEvent;
use App\Models\User;
use App\Modules\Telegram\DTO\TelegramScreen;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\Cursor;

/** @return list<array<string, mixed>> */
function telegramHistoryCommands(TelegramScreen $screen, string $action): array
{
    return collect($screen->rows)->flatten(1)->pluck('command')->filter()->where('action', $action)->values()->all();
}

test('telegram history filters the selected dogs actions by week and month without changing records', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create();
    $recent = PetHistoryEntry::factory()->create(['pet_id' => $pet->id, 'occurred_at' => now()->subDay()]);
    $older = PetHistoryEntry::factory()->create(['pet_id' => $pet->id, 'occurred_at' => now()->subDays(10)]);
    PetHistoryEntry::factory()->create(['pet_id' => $pet->id, 'kind' => 'thought']);
    PetHistoryEntry::factory()->create();
    $menu = app(PetHistoryMenu::class);

    $week = $menu->index($pet->user, 'en', ['petId' => $pet->id]);
    $month = $menu->index($pet->user, 'en', ['petId' => $pet->id, 'period' => 30]);

    expect(array_column(telegramHistoryCommands($week, 'pet.history.entry'), 'entryId'))->toBe([$recent->id]);
    expect(array_column(telegramHistoryCommands($month, 'pet.history.entry'), 'entryId'))->toBe([$recent->id, $older->id]);
    $this->assertDatabaseCount('pet_history_entries', 4);
});

test('telegram history pages traverse equal timestamps across query cursors without omissions', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create();
    $entries = PetHistoryEntry::factory()->count(25)->create(['pet_id' => $pet->id]);
    $menu = app(PetHistoryMenu::class);
    $command = ['petId' => $pet->id];
    $seen = [];
    for ($page = 0; $page < 6; $page++) {
        $screen = $menu->index($pet->user, 'en', $command);
        $visible = telegramHistoryCommands($screen, 'pet.history.entry');
        expect(count($visible))->toBeLessThanOrEqual(6);
        $seen = [...$seen, ...array_column($visible, 'entryId')];
        $next = collect($screen->rows)->flatten(1)->firstWhere('text', trans('telegram.next', [], 'en'));
        if ($next === null) {
            break;
        }
        $command = $next['command'];
    }

    expect($seen)->toBe($entries->reverse()->values()->modelKeys());
    expect(array_unique($seen))->toHaveCount(25);
    $previous = collect($screen->rows)->flatten(1)->firstWhere('text', trans('telegram.previous', [], 'en'));
    $back = $menu->index($pet->user, 'en', $previous['command']);
    expect(array_column(telegramHistoryCommands($back, 'pet.history.entry'), 'entryId'))->toBe($entries->reverse()->values()->slice(18, 2)->values()->modelKeys());
});

test('telegram history details show escaped snapshots and useful changes without internal fields', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create();
    $entry = PetHistoryEntry::factory()->create(['pet_id' => $pet->id,
        'title' => ['en' => '<Lesson>'], 'message' => ['en' => '<Well done>'],
        'details' => ['name' => ['en' => '<Tracker>'], 'coins' => -12, 'experienceAwarded' => 3, 'durationSeconds' => 60,
            'changes' => [['metric' => 'health', 'before' => 50, 'after' => 60, 'delta' => 10, 'unit' => 'percent']],
            'private_counter' => 'not-for-player'],
    ]);

    $screen = app(PetHistoryMenu::class)->show($pet->user, 'en', ['petId' => $pet->id, 'entryId' => $entry->id]);

    expect($screen->text)->toContain('&lt;Lesson&gt;', '&lt;Well done&gt;', '&lt;Tracker&gt;', '-12', '+10%', '50%', '60%')
        ->not->toContain('<Lesson>', 'private_counter', 'not-for-player');
    expect(telegramHistoryCommands($screen, 'pet.history'))->not->toBeEmpty();
});

test('telegram history can open a displayed record after new actions displace it from the list page', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create();
    PetHistoryEntry::factory()->count(21)->create(['pet_id' => $pet->id]);
    $menu = app(PetHistoryMenu::class);
    $list = $menu->index($pet->user, 'en', ['petId' => $pet->id, 'offset' => 18]);
    $command = telegramHistoryCommands($list, 'pet.history.entry')[1];
    $this->travel(1)->second();
    PetHistoryEntry::factory()->count(2)->create(['pet_id' => $pet->id]);

    $detail = $menu->show($pet->user, 'en', $command);

    expect($detail->text)->toContain('Feeding');
    expect(telegramHistoryCommands($detail, 'pet.history')[0])->toMatchArray(['petId' => $pet->id, 'offset' => 18]);
});

test('telegram history thought and event filters preserve the selected period', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create();
    PetHistoryEntry::factory()->create(['pet_id' => $pet->id]);
    $thought = PetHistoryEntry::factory()->create(['pet_id' => $pet->id, 'kind' => 'thought', 'event_code' => 'thought.hungry', 'occurred_at' => now()->subDays(10)]);
    PetHistoryEntry::factory()->create(['pet_id' => $pet->id, 'kind' => 'thought', 'event_code' => 'thought.tired']);
    PetHistoryEvent::factory()->thought()->create(['code' => 'thought.hungry']);
    $menu = app(PetHistoryMenu::class);

    $screen = $menu->index($pet->user, 'en', ['petId' => $pet->id, 'kind' => 'thought', 'period' => 30, 'event' => 'thought.hungry']);
    $events = $menu->events($pet->user, 'en', ['petId' => $pet->id, 'kind' => 'thought', 'period' => 30]);

    expect(array_column(telegramHistoryCommands($screen, 'pet.history.entry'), 'entryId'))->toBe([$thought->id]);
    expect(collect(telegramHistoryCommands($events, 'pet.history'))->firstWhere('event', 'thought.hungry'))
        ->toMatchArray(['petId' => $pet->id, 'kind' => 'thought', 'period' => 30]);
});

test('telegram history refuses foreign dogs and foreign entries but keeps archived history available', function () {
    $pet = Pet::factory()->deceased()->create();
    $entry = PetHistoryEntry::factory()->create(['pet_id' => $pet->id]);
    $foreign = PetHistoryEntry::factory()->create();
    $menu = app(PetHistoryMenu::class);

    expect(telegramHistoryCommands($menu->index($pet->user, 'en', ['petId' => $pet->id]), 'pet.history.entry'))->toHaveCount(1);
    expect(fn () => $menu->index(User::factory()->create(), 'en', ['petId' => $pet->id]))->toThrow(ModelNotFoundException::class);
    expect(fn () => $menu->show($pet->user, 'en', ['petId' => $pet->id, 'entryId' => $foreign->id]))->toThrow(ModelNotFoundException::class);
    $entry->delete();
    expect(fn () => $menu->show($pet->user, 'en', ['petId' => $pet->id, 'entryId' => $entry->id]))->toThrow(ModelNotFoundException::class);
});

test('telegram history rejects malformed cursors before a database error', function (string $cursor) {
    $pet = Pet::factory()->create();

    expect(fn () => app(PetHistoryMenu::class)->index($pet->user, 'en', ['petId' => $pet->id, 'cursor' => $cursor]))
        ->toThrow(ModelNotFoundException::class);
})->with([
    'invalid encoding' => ['not-a-cursor'],
    'missing parameters' => [base64_encode('{"_pointsToNextItems":true}')],
    'invalid date' => [(new Cursor(['id' => 1, 'occurred_at' => 'bad-date']))->encode()],
    'invalid direction' => [base64_encode('{"id":1,"occurred_at":"2026-11-02 12:00:00","_pointsToNextItems":[]}')],
]);

test('telegram empty history explains the current filters and retains navigation', function () {
    $pet = Pet::factory()->create();

    $screen = app(PetHistoryMenu::class)->index($pet->user, 'en', ['petId' => $pet->id]);

    expect($screen->text)->toContain(trans('telegram.history.empty', [], 'en'));
    expect(telegramHistoryCommands($screen, 'pet.history'))->not->toBeEmpty();
    expect(telegramHistoryCommands($screen, 'pet'))->toContain(['action' => 'pet', 'petId' => $pet->id]);
});

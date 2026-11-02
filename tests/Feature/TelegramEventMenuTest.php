<?php

use App\Http\Telegram\Menus\EventMenu;
use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\Pet;
use App\Models\PetTitle;
use App\Models\User;
use App\Modules\Telegram\DTO\TelegramScreen;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\Cursor;

/** @return list<array<string, mixed>> */
function telegramEventCommands(TelegramScreen $screen, string $action): array
{
    return collect($screen->rows)->flatten(1)->pluck('command')->filter()->where('action', $action)->values()->all();
}

test('telegram event calendars filter competition and exhibition and traverse native cursor boundaries both ways', function () {
    $this->freezeSecond();
    config(['game-events.disciplines' => [], 'game-events.special_calendar.events' => [], 'game-events.special_calendar.days_ahead' => 0]);
    $user = User::factory()->create(['locale' => 'en']);
    $ids = [];
    foreach (range(1, 31) as $minute) {
        $starts = now()->addHours(2)->addMinutes($minute);
        $ids[] = GameEvent::factory()->create(['starts_at' => $starts, 'closes_at' => $starts->subMinutes(15), 'ends_at' => $starts->addMinutes(10)])->id;
    }
    $show = GameEvent::factory()->create(['discipline' => 'conformation', 'frequency' => 'monthly']);
    $menu = app(EventMenu::class);
    $command = ['kind' => 'competition'];
    $seen = [];
    for ($page = 0; $page < 8; $page++) {
        $screen = $menu->index($user, 'en', $command);
        $visible = telegramEventCommands($screen, 'events.show');
        expect(count($visible))->toBeLessThanOrEqual(6);
        $seen = [...$seen, ...array_column($visible, 'eventId')];
        $next = collect($screen->rows)->flatten(1)->firstWhere('text', trans('telegram.next', [], 'en'));
        if ($next === null) {
            break;
        }
        $command = $next['command'];
    }
    expect($seen)->toBe($ids);
    $previous = collect($screen->rows)->flatten(1)->firstWhere('text', trans('telegram.previous', [], 'en'));
    $back = $menu->index($user, 'en', $previous['command']);
    expect(array_column(telegramEventCommands($back, 'events.show'), 'eventId'))->toBe(array_slice($ids, 24, 6));
    $exhibitions = $menu->index($user, 'en', ['kind' => 'exhibition', 'frequency' => 'monthly']);
    expect(array_column(telegramEventCommands($exhibitions, 'events.show'), 'eventId'))->toBe([$show->id]);
});

test('telegram event details expose site terms and each owned entry without changing gameplay', function () {
    $this->freezeSecond();
    $user = User::factory()->create(['locale' => 'en']);
    $event = GameEvent::factory()->create(['discipline' => 'conformation']);
    $first = GameEventEntry::factory()->for($event, 'event')->for($user)->create();
    $second = GameEventEntry::factory()->for($event, 'event')->for($user)->create();
    $menu = app(EventMenu::class);

    $screen = $menu->show($user, 'en', ['eventId' => $event->id]);
    $own = $menu->own($user, 'en', ['eventId' => $event->id]);

    expect($screen->text)->toContain('25', 'Registration closes', '100');
    expect(array_column(telegramEventCommands($own, 'events.entry'), 'entryId'))->toBe([$first->id, $second->id]);
    expect(telegramEventCommands($screen, 'events.results'))->not->toBeEmpty();
    $this->assertDatabaseCount('currency_transactions', 0);
});

test('telegram event results retain assessments standings stage replay and awards for unranked entries', function () {
    $this->freezeSecond();
    $pet = Pet::factory()->create(['name' => '<Rex>']);
    $event = GameEvent::factory()->create(['discipline' => 'conformation', 'status' => 'settled']);
    $rules = $event->rules;
    $rules['version'] = 3;
    $event->update(['rules' => $rules]);
    $entry = GameEventEntry::factory()->for($event, 'event')->for($pet->user)->create(['pet_id' => $pet->id, 'status' => 'completed', 'rank' => null,
        'snapshot' => ['name' => '<Snapshot>'], 'prize' => 0,
        'result' => ['version' => 3, 'score' => 92.5, 'time' => 45, 'penalties' => 0, 'grade' => 'excellent', 'qualified' => true,
            'standings' => [['key' => 'overall', 'rank' => 1, 'score' => 92.5]],
            'stages' => [['key' => 'type', 'decision' => 'balanced', 'time' => 10, 'score' => 90, 'penalties' => 0, 'note' => 'clean']]],
    ]);
    PetTitle::factory()->create(['pet_id' => $pet->id, 'game_event_entry_id' => $entry->id, 'kind' => 'certificate', 'code' => 'CAC', 'discipline' => 'conformation', 'details' => []]);

    $screen = app(EventMenu::class)->entry($pet->user, 'en', ['eventId' => $event->id, 'entryId' => $entry->id]);

    expect($screen->text)->toContain('&lt;Snapshot&gt;', 'Excellent', 'CAC', '92.5', 'Breed type')->not->toContain('<Snapshot>');
    expect(telegramEventCommands($screen, 'events.prepare'))->toBeEmpty();
    expect(telegramEventCommands($screen, 'events.entry'))->toContain(['action' => 'events.entry', 'eventId' => $event->id, 'entryId' => $entry->id]);
});

test('telegram result pages show only the selected division and navigate all divisions', function () {
    $this->freezeSecond();
    $user = User::factory()->create();
    $event = GameEvent::factory()->create();
    $first = GameEventEntry::factory()->for($event, 'event')->create(['division' => 'novice:medium']);
    $second = GameEventEntry::factory()->for($event, 'event')->create(['division' => 'open:large']);
    $menu = app(EventMenu::class);

    $screen = $menu->results($user, 'en', ['eventId' => $event->id, 'division' => 'open:large']);
    $divisions = $menu->divisions($user, 'en', ['eventId' => $event->id]);

    expect(array_column(telegramEventCommands($screen, 'events.entry'), 'entryId'))->toBe([$second->id]);
    expect(telegramEventCommands($divisions, 'events.results'))->toContain(['action' => 'events.results', 'eventId' => $event->id, 'division' => $first->division]);
});

test('telegram event entry details do not expose another owners unpublished plan or cancelled entry', function () {
    $this->freezeSecond();
    $user = User::factory()->create();
    $event = GameEvent::factory()->create();
    $foreign = GameEventEntry::factory()->for($event, 'event')->create(['plan' => ['stages' => ['bold', 'bold', 'bold']], 'gear_ids' => [123]]);
    $menu = app(EventMenu::class);

    $screen = $menu->entry($user, 'en', ['eventId' => $event->id, 'entryId' => $foreign->id]);

    expect($screen->text)->not->toContain('Bold', '123');
    expect(telegramEventCommands($screen, 'events.prepare'))->toBeEmpty();
    $foreign->update(['status' => 'cancelled']);
    expect(fn () => $menu->entry($user, 'en', ['eventId' => $event->id, 'entryId' => $foreign->id]))->toThrow(ModelNotFoundException::class);
});

test('telegram event calendars refuse malformed native cursor schemas', function (string $cursor) {
    $user = User::factory()->create();

    expect(fn () => app(EventMenu::class)->index($user, 'en', ['kind' => 'competition', 'cursor' => $cursor]))
        ->toThrow(ModelNotFoundException::class);
})->with([
    ['invalid'], [(new Cursor(['id' => '1e99', 'starts_at' => 'bad-date']))->encode()],
    [base64_encode('{"id":1,"starts_at":"2026-11-02 10:00:00","_pointsToNextItems":[]}')],
]);

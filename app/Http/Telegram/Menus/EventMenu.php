<?php

namespace App\Http\Telegram\Menus;

use App\Models\GameEvent;
use App\Models\User;
use App\Modules\Pets\Enums\GameEventDiscipline;
use App\Modules\Pets\Queries\GetGameEvents;
use App\Modules\Pets\Services\GameEventSchedule;
use App\Modules\Telegram\DTO\TelegramScreen;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\Validator;

/** @phpstan-import-type Command from MenuView */
final class EventMenu
{
    public function __construct(private MenuView $view, private EventView $presentation, private GetGameEvents $events, private GameEventSchedule $calendar, private EventResultMenu $results) {}

    /** @param Command $command */
    public function index(User $user, string $locale, array $command): TelegramScreen
    {
        $cursor = $this->cursor($command['cursor'] ?? null);
        $kind = ($command['kind'] ?? 'competition') === 'exhibition' ? 'exhibition' : 'competition';
        $filters = ['kind' => $kind];
        if (in_array($command['frequency'] ?? null, ['daily', 'weekly', 'monthly'], true)) {
            $filters['frequency'] = $command['frequency'];
        }
        if ($cursor === null && (int) ($command['offset'] ?? 0) === 0) {
            $this->calendar->ensureUpcoming();
        }
        $page = $this->events->index($user, $filters, $cursor);
        $offset = min(max(0, (int) ($command['offset'] ?? 0)), intdiv(max(0, count($page['events']) - 1), MenuView::PAGE_SIZE) * MenuView::PAGE_SIZE);
        $navigation = ['action' => 'events', ...$filters];
        $rows = [];
        foreach (array_slice($page['events'], $offset, MenuView::PAGE_SIZE) as $event) {
            $label = $this->view->dateTime($event['startsAt'], $user).' · '.$this->presentation->eventName($event, $locale);
            $rows[] = [$this->view->commandButton($label, ['action' => 'events.show', 'eventId' => $event['id']])];
        }
        $rows = [...$rows, ...$this->view->pageNavigation(count($page['events']), $offset, $cursor?->encode(), $page['nextCursor'], $page['previousCursor'], $navigation, $locale,
            previousOffset: intdiv(GetGameEvents::PAGE_SIZE - 1, MenuView::PAGE_SIZE) * MenuView::PAGE_SIZE)];
        $rows[] = [$this->view->button('event.competitions', 'events', $locale, ['kind' => 'competition']), $this->view->button('event.exhibitions', 'events', $locale, ['kind' => 'exhibition'])];
        $rows[] = [$this->view->button('event.all_frequencies', 'events', $locale, ['kind' => $kind])];
        $frequencies = [];
        foreach (['daily', 'weekly', 'monthly'] as $frequency) {
            $frequencies[] = $this->view->button('event.frequency.'.$frequency, 'events', $locale, ['kind' => $kind, 'frequency' => $frequency]);
        }
        $rows[] = $frequencies;
        $rows[] = [$this->view->button('refresh', 'events', $locale, $navigation)];

        return $this->view->screen($this->view->translate('event.'.($kind === 'exhibition' ? 'exhibitions' : 'competitions'), $locale)
            .($page['events'] === [] ? "\n\n".$this->view->translate('event.empty', $locale) : ''), $rows, $locale);
    }

    /** @param Command $command */
    public function show(User $user, string $locale, array $command, ?string $notice = null): TelegramScreen
    {
        $model = GameEvent::query()->findOrFail((int) $command['eventId']);
        $state = $this->events->showState($user, $model, $locale, selectedPetId: isset($command['petId']) ? (int) $command['petId'] : null);
        $event = $state['event'];
        $rows = [];
        if ($event['canRegister']) {
            $rows[] = [$this->view->button('event.register', 'events.prepare', $locale, ['eventId' => $model->id])];
        }
        if ($state['ownEntries'] !== []) {
            $rows[] = [$this->view->button('event.own_entries', 'events.own', $locale, ['eventId' => $model->id])];
        }
        $rows[] = [$this->view->button('event.results', 'events.results', $locale, ['eventId' => $model->id]), $this->view->button('refresh', 'events.show', $locale, ['eventId' => $model->id])];
        foreach ($event['groupContests'] ?? [] as $contest) {
            $rows[] = [$this->view->commandButton($contest['name'], ['action' => 'events.show', 'eventId' => $contest['id']])];
        }
        if (isset($event['linkedShow'])) {
            $rows[] = [$this->view->commandButton($event['linkedShow']['name'], ['action' => 'events.show', 'eventId' => $event['linkedShow']['id']])];
        }

        return $this->view->screen(($notice === null ? '' : $notice."\n\n").$this->presentation->eventText($event, $user, $locale), $rows, $locale,
            ['action' => 'events', 'kind' => GameEventDiscipline::from($model->discipline)->isExhibition() ? 'exhibition' : 'competition']);
    }

    /** @param Command $command */
    public function own(User $user, string $locale, array $command): TelegramScreen
    {
        $event = GameEvent::query()->findOrFail((int) $command['eventId']);
        $entries = $this->events->showState($user, $event, $locale)['ownEntries'];
        $offset = max(0, (int) ($command['offset'] ?? 0));
        $rows = [];
        foreach (array_slice($entries, $offset, MenuView::PAGE_SIZE) as $entry) {
            $rows[] = [$this->view->commandButton($entry['name'].' · '.$this->view->translate('event.entry_status.'.$entry['status'], $locale), ['action' => 'events.entry', 'eventId' => $event->id, 'entryId' => $entry['id']])];
        }
        $rows = [...$rows, ...$this->view->offsetNavigation(count($entries), $offset, ['action' => 'events.own', 'eventId' => $event->id], $locale)];

        return $this->view->screen($this->view->translate($entries === [] ? 'event.no_entries' : 'event.own_entries', $locale), $rows, $locale, ['action' => 'events.show', 'eventId' => $event->id]);
    }

    /** @param Command $command */
    public function entry(User $user, string $locale, array $command, ?string $notice = null): TelegramScreen
    {
        return $this->results->entry($user, $locale, $command, $notice);
    }

    /** @param Command $command */
    public function results(User $user, string $locale, array $command): TelegramScreen
    {
        return $this->results->index($user, $locale, $command);
    }

    /** @param Command $command */
    public function divisions(User $user, string $locale, array $command): TelegramScreen
    {
        return $this->results->divisions($user, $locale, $command);
    }

    private function cursor(mixed $encoded): ?Cursor
    {
        if ($encoded === null) {
            return null;
        }
        if (! is_string($encoded) || strlen($encoded) > 1000) {
            throw new ModelNotFoundException;
        }
        $parameters = json_decode(base64_decode(strtr($encoded, '-_', '+/'), true) ?: '', true);
        if (! is_array($parameters) || ! is_bool($parameters['_pointsToNextItems'] ?? null)
            || Validator::make($parameters, ['id' => ['required', 'regex:/^[1-9][0-9]{0,17}$/'], 'starts_at' => ['required', 'string', 'date_format:Y-m-d H:i:s']])->fails()) {
            throw new ModelNotFoundException;
        }

        return new Cursor(['starts_at' => $parameters['starts_at'], 'id' => $parameters['id']], $parameters['_pointsToNextItems']);
    }
}

<?php

namespace App\Http\Telegram\Menus;

use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\User;
use App\Modules\Pets\Queries\GetGameEvents;
use App\Modules\Telegram\DTO\TelegramScreen;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

/** @phpstan-import-type Command from MenuView */
final class EventResultMenu
{
    public function __construct(private MenuView $view, private EventView $presentation, private GetGameEvents $events) {}

    /** @param Command $command */
    public function index(User $user, string $locale, array $command): TelegramScreen
    {
        $event = GameEvent::query()->findOrFail((int) $command['eventId']);
        $data = $this->events->showState($user, $event, $locale, $command['division'] ?? null)['event'];
        $entries = $data['entries'];
        $offset = max(0, (int) ($command['offset'] ?? 0));
        $navigation = ['action' => 'events.results', 'eventId' => $event->id, 'division' => $data['activeDivision']];
        $rows = [];
        foreach (array_slice($entries, $offset, MenuView::PAGE_SIZE) as $entry) {
            $label = ($entry['rank'] === null ? '' : $entry['rank'].'. ').$entry['name'].($entry['isNpc'] ? ' · '.$this->view->translate('event.club_dog', $locale) : '');
            $rows[] = [$this->view->commandButton($label, ['action' => 'events.entry', 'eventId' => $event->id, 'entryId' => $entry['id']])];
        }
        $rows = [...$rows, ...$this->view->offsetNavigation(count($entries), $offset, $navigation, $locale)];
        $rows[] = [$this->view->button('event.divisions', 'events.divisions', $locale, ['eventId' => $event->id]), $this->view->button('refresh', 'events.results', $locale, $navigation)];

        return $this->view->screen($this->view->translate('event.results', $locale).' · '.$this->presentation->eventName($data, $locale)
            .($entries === [] ? "\n\n".$this->view->translate('event.no_results', $locale) : ''), $rows, $locale, ['action' => 'events.show', 'eventId' => $event->id]);
    }

    /** @param Command $command */
    public function divisions(User $user, string $locale, array $command): TelegramScreen
    {
        $event = GameEvent::query()->findOrFail((int) $command['eventId']);
        $divisions = $this->events->showState($user, $event, $locale)['event']['divisions'];
        $offset = max(0, (int) ($command['offset'] ?? 0));
        $rows = [];
        foreach (array_slice($divisions, $offset, MenuView::PAGE_SIZE) as $division) {
            $rows[] = [$this->view->commandButton($division['label'], ['action' => 'events.results', 'eventId' => $event->id, 'division' => $division['key']])];
        }
        $rows = [...$rows, ...$this->view->offsetNavigation(count($divisions), $offset, ['action' => 'events.divisions', 'eventId' => $event->id], $locale)];

        return $this->view->screen($this->view->translate('event.divisions', $locale), $rows, $locale, ['action' => 'events.results', 'eventId' => $event->id]);
    }

    /** @param Command $command */
    public function entry(User $user, string $locale, array $command, ?string $notice = null): TelegramScreen
    {
        $event = GameEvent::query()->findOrFail((int) $command['eventId']);
        $model = GameEventEntry::query()->where('game_event_id', $event->id)->findOrFail((int) $command['entryId']);
        if ($model->user_id !== $user->id && ! in_array($model->status, ['registered', 'frozen', 'completed'], true)) {
            throw new ModelNotFoundException;
        }
        $data = $this->events->showState($user, $event, $locale, $model->division, $model->pet_id);
        $entry = null;
        foreach ([...$data['ownEntries'], ...$data['event']['entries']] as $candidate) {
            if ($candidate['id'] === $model->id) {
                $entry = $candidate;
                break;
            }
        }
        if ($entry === null) {
            throw new ModelNotFoundException;
        }
        $rows = [];
        if ($model->user_id === $user->id && $this->presentation->editable($data['event'], $entry)) {
            $rows[] = [$this->view->button('event.update', 'events.prepare', $locale, ['eventId' => $event->id, 'entryId' => $model->id])];
            $rows[] = [$this->view->button('event.cancel_title', 'events.cancel-confirm', $locale, ['eventId' => $event->id, 'entryId' => $model->id])];
        }
        $text = ($notice === null ? '' : $notice."\n\n").$this->presentation->entryText($entry, $user, $locale);
        if ($model->user_id === $user->id && $entry['gearIds'] !== []) {
            $names = [];
            foreach ($this->events->registrationEquipment($user, $locale) as $item) {
                if (in_array($item['id'], $entry['gearIds'], true)) {
                    $names[] = Str::limit($item['name'], 80);
                }
            }
            if ($names !== []) {
                $text .= "\n".$this->view->translate('event.selected_gear', $locale).': '.implode(', ', $names);
            }
        }
        $rows[] = [$this->view->button('refresh', 'events.entry', $locale, ['eventId' => $event->id, 'entryId' => $model->id])];

        return $this->view->screen($text, $rows, $locale, ['action' => 'events.show', 'eventId' => $event->id]);
    }
}

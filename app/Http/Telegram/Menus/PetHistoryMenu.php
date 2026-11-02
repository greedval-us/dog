<?php

namespace App\Http\Telegram\Menus;

use App\Models\User;
use App\Modules\Pets\Queries\GetPetHistory;
use App\Modules\Telegram\DTO\TelegramScreen;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\Validator;

/**
 * @phpstan-import-type Command from MenuView
 *
 * @phpstan-type Filters array{action: string, petId: int, kind: string, period: int, event: string|null, cursor: string|null, offset: int}
 */
final class PetHistoryMenu
{
    public function __construct(private MenuView $view, private GetPetHistory $history, private PetHistoryView $entries) {}

    /** @param Command $command */
    public function index(User $user, string $locale, array $command): TelegramScreen
    {
        $filters = $this->filters($command);
        $page = $this->history->handle($user, $filters['petId'], $locale, $filters['kind'], $filters['period'], $filters['event'], $this->cursor($filters['cursor']));
        $filters['offset'] = min($filters['offset'], intdiv(max(0, count($page['data']) - 1), MenuView::PAGE_SIZE) * MenuView::PAGE_SIZE);
        $text = $this->view->translate('history.title', $locale).' · '.$this->view->translate('history.'.($filters['kind'] === 'action' ? 'actions' : 'thoughts'), $locale)
            ."\n".$this->view->translate('history.period', $locale, ['days' => $filters['period']]);
        $rows = [];
        foreach (array_slice($page['data'], $filters['offset'], MenuView::PAGE_SIZE) as $entry) {
            $label = $this->view->dateTime($entry['occurredAt'], $user).' · '.$entry['title'];
            $rows[] = [$this->view->commandButton($label, [...$filters, 'action' => 'pet.history.entry', 'entryId' => $entry['id']])];
        }
        if ($page['data'] === []) {
            $text .= "\n\n".$this->view->translate('history.empty', $locale);
        }
        $rows = [...$rows, ...$this->view->pageNavigation(count($page['data']), $filters['offset'], $filters['cursor'], $page['nextCursor'], $page['previousCursor'], $filters, $locale,
            previousOffset: intdiv(GetPetHistory::PAGE_SIZE - 1, MenuView::PAGE_SIZE) * MenuView::PAGE_SIZE)];
        $tabs = [];
        foreach (['action' => 'actions', 'thought' => 'thoughts'] as $kind => $label) {
            $tabs[] = $this->view->button('history.'.$label, 'pet.history', $locale, ['petId' => $filters['petId'], 'kind' => $kind, 'period' => $filters['period']]);
        }
        $rows[] = $tabs;
        $rows[] = [$this->view->button('history.week', 'pet.history', $locale, [...$filters, 'period' => 7, 'offset' => 0, 'cursor' => null]),
            $this->view->button('history.month', 'pet.history', $locale, [...$filters, 'period' => 30, 'offset' => 0, 'cursor' => null])];
        $rows[] = [$this->view->button('history.events', 'pet.history.events', $locale, [...$filters, 'action' => 'pet.history.events', 'offset' => 0, 'cursor' => null]),
            $this->view->button('refresh', 'pet.history', $locale, $filters)];

        return $this->view->screen($text, $rows, $locale, ['action' => 'pet', 'petId' => $filters['petId']]);
    }

    /** @param Command $command */
    public function show(User $user, string $locale, array $command): TelegramScreen
    {
        $filters = $this->filters($command);
        $this->cursor($filters['cursor']);
        $entry = $this->history->find($user, $filters['petId'], (int) $command['entryId'], $locale, $filters['kind'], $filters['period'], $filters['event']);

        return $this->view->screen($this->entries->text($entry, $user, $locale), [], $locale, $filters);
    }

    /** @param Command $command */
    public function events(User $user, string $locale, array $command): TelegramScreen
    {
        $filters = $this->filters($command);
        $events = $this->history->handle($user, $filters['petId'], $locale, $filters['kind'], $filters['period'])['events'];
        $rows = [[$this->view->button('history.all_events', 'pet.history', $locale, [...$filters, 'event' => null, 'offset' => 0])]];
        foreach (array_slice($events, $filters['offset'], MenuView::PAGE_SIZE) as $event) {
            $rows[] = [$this->view->commandButton($event['name'], [...$filters, 'event' => $event['code'], 'offset' => 0])];
        }
        $rows = [...$rows, ...$this->view->offsetNavigation(count($events), $filters['offset'], [...$filters, 'action' => 'pet.history.events'], $locale)];

        return $this->view->screen($this->view->translate('history.events', $locale), $rows, $locale, [...$filters, 'offset' => 0]);
    }

    /**
     * @param  Command  $command
     * @return Filters
     */
    private function filters(array $command): array
    {
        $cursor = $command['cursor'] ?? null;
        $event = $command['event'] ?? null;
        if (($cursor !== null && ! is_string($cursor)) || ($event !== null && (! is_string($event) || strlen($event) > 80 || ! preg_match('/^[a-z][a-z0-9_.]*$/', $event)))) {
            throw new ModelNotFoundException;
        }

        return ['action' => 'pet.history', 'petId' => (int) $command['petId'], 'kind' => ($command['kind'] ?? 'action') === 'thought' ? 'thought' : 'action',
            'period' => (int) ($command['period'] ?? 7) === 30 ? 30 : 7, 'event' => $event, 'cursor' => $cursor, 'offset' => max(0, (int) ($command['offset'] ?? 0))];
    }

    private function cursor(?string $encoded): ?Cursor
    {
        if ($encoded === null) {
            return null;
        }
        $parameters = json_decode(base64_decode(strtr($encoded, '-_', '+/'), true) ?: '', true);
        if (strlen($encoded) > 2048 || ! is_array($parameters) || ! is_bool($parameters['_pointsToNextItems'] ?? null)
            || Validator::make($parameters, ['id' => ['required', 'regex:/^[1-9][0-9]{0,17}$/'], 'occurred_at' => ['required', 'string', 'date_format:Y-m-d H:i:s']])->fails()) {
            throw new ModelNotFoundException;
        }

        return new Cursor(['id' => $parameters['id'], 'occurred_at' => $parameters['occurred_at']], $parameters['_pointsToNextItems']);
    }
}

<?php

namespace App\Http\Telegram\Menus;

use App\Models\GameEvent;
use App\Models\User;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Queries\GetGameEvents;
use App\Modules\Telegram\DTO\TelegramScreen;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

/**
 * @phpstan-import-type Command from MenuView
 * @phpstan-import-type State from MenuView
 */
final class EventRegistrationMenu
{
    public function __construct(
        private MenuView $view,
        private GetGameEvents $events,
        private EventDraftChoices $choices,
        private EventDraftView $draftView,
    ) {}

    /** @param Command $command */
    public function prepare(User $user, string $locale, array $command): TelegramScreen
    {
        $event = GameEvent::query()->findOrFail((int) $command['eventId']);
        $entry = isset($command['entryId']) ? $event->entries()->where('user_id', $user->id)->findOrFail((int) $command['entryId']) : null;
        $petId = $entry->pet_id ?? ($command['petId'] ?? null);
        if ($entry === null && $petId !== null) {
            $entry = $event->entries()->where('user_id', $user->id)->where('pet_id', (int) $petId)->first();
        }
        if ($entry !== null && $entry->status !== 'registered') {
            return $this->view->screen($this->view->translate('event.entry_status.'.$entry->status, $locale),
                [[$this->view->button('event.view_entry', 'events.entry', $locale, ['eventId' => $event->id, 'entryId' => $entry->id])]], $locale,
                ['action' => 'events.show', 'eventId' => $event->id]);
        }
        $dogs = $this->events->registrationDogs($user, $locale, $event);
        if ($petId === null) {
            return $this->draftView->dogs($user, $event, $dogs, $locale, max(0, (int) ($command['offset'] ?? 0)));
        }
        $dog = collect($dogs)->firstWhere('id', (int) $petId);
        if ($dog === null) {
            throw new ModelNotFoundException;
        }
        $reasons = $this->choices->dogReasons($event, $dog);
        if (! $this->choices->isOpen($event)) {
            $reasons[] = $this->view->translate('event.registration_closed', $locale);
        }
        if ($reasons !== []) {
            return $this->view->screen(implode("\n", [Str::limit((string) $dog['name'], 80), ...$reasons]), [], $locale,
                ['action' => 'events.show', 'eventId' => $event->id]);
        }
        $plan = $entry->plan ?? $this->choices->initialPlan($event, $dog);
        $state = ['eventDraft' => [
            'userId' => $user->id, 'eventId' => $event->id, 'petId' => (int) $dog['id'], 'entryId' => $entry?->id,
            'fee' => (int) $event->rules['fee'], 'token' => (string) Str::uuid(), 'plan' => $plan, 'gearIds' => $entry->gear_ids ?? [],
        ]];

        return $this->draft($user, $locale, ['action' => 'events.draft.show', 'step' => $entry === null ? $this->choices->steps($event)[0] : 'review'], $state);
    }

    /**
     * @param  Command  $command
     * @param  State  $state
     */
    public function draft(User $user, string $locale, array $command, array $state): TelegramScreen
    {
        $saved = new SavedEventDraft($user, $state);
        $event = GameEvent::query()->findOrFail($saved->eventId);
        if (count($saved->plan['stages']) !== count($event->rules['stages'])) {
            throw new PetUnavailable('telegram.errors.stale');
        }
        $dog = collect($this->events->registrationDogs($user, $locale, $event))->firstWhere('id', $saved->petId);
        if ($dog === null) {
            throw new ModelNotFoundException;
        }
        $entry = $saved->entryId === null ? null : $event->entries()->where('user_id', $user->id)->where('pet_id', $saved->petId)->findOrFail($saved->entryId);
        $values = $saved->toArray();
        $action = (string) ($command['action'] ?? 'events.draft.show');
        $step = (string) ($command['step'] ?? match ($action) {
            'events.draft.option' => 'option', 'events.draft.member' => 'members',
            'events.draft.stage' => 'stages', 'events.draft.gear' => 'gear', default => $this->choices->steps($event)[0],
        });
        if (! in_array($step, $this->choices->steps($event), true)) {
            throw new PetUnavailable('telegram.errors.stale');
        }
        $equipment = $this->choices->equipment($user, $event, $dog, $locale);
        if ($action !== 'events.draft.show') {
            if (! $this->choices->isOpen($event) || $entry !== null && $entry->status !== 'registered') {
                throw new PetUnavailable('events.errors.closed');
            }
            $values = $this->choices->select($event, $dog, $equipment, $values, $command);
            if ($values !== $saved->toArray()) {
                $values['token'] = (string) Str::uuid();
            }
        }

        return $this->draftView->render($user, $event, $dog, $equipment, $values, $step, max(0, (int) ($command['offset'] ?? 0)), $locale, $entry);
    }
}

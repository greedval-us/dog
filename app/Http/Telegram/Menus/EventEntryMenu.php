<?php

namespace App\Http\Telegram\Menus;

use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\User;
use App\Modules\Pets\Actions\CancelGameEventEntry;
use App\Modules\Pets\Actions\RegisterGameEvent;
use App\Modules\Pets\Actions\UpdateGameEventEntry;
use App\Modules\Pets\Exceptions\GameEventUnavailable;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Telegram\DTO\TelegramScreen;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

/**
 * @phpstan-import-type Command from MenuView
 * @phpstan-import-type State from MenuView
 */
final class EventEntryMenu
{
    public function __construct(
        private MenuView $view,
        private EventMenu $events,
        private RegisterGameEvent $registerEntry,
        private UpdateGameEventEntry $updateEntry,
        private CancelGameEventEntry $cancelEntry,
    ) {}

    /** @param State $state */
    public function register(User $user, string $locale, array $state): TelegramScreen
    {
        $draft = new SavedEventDraft($user, $state);
        if ($draft->entryId !== null) {
            throw new PetUnavailable('telegram.errors.stale');
        }
        $entry = $this->registerEntry->handle($user, $draft->eventId, $draft->petId, $draft->plan, $draft->gearIds, $draft->fee, $draft->token);

        return $this->events->entry($user->refresh(), $locale, ['eventId' => $draft->eventId, 'entryId' => $entry->id],
            $this->view->translate('event.registered', $locale));
    }

    /** @param State $state */
    public function update(User $user, string $locale, array $state): TelegramScreen
    {
        $draft = new SavedEventDraft($user, $state);
        if ($draft->entryId === null) {
            throw new PetUnavailable('telegram.errors.stale');
        }
        $this->ownedEntry($user, $draft->eventId, $draft->entryId, $draft->petId);
        $entry = $this->updateEntry->handle($user, $draft->entryId, $draft->plan, $draft->gearIds);

        return $this->events->entry($user->refresh(), $locale, ['eventId' => $draft->eventId, 'entryId' => $entry->id],
            $this->view->translate('event.updated', $locale));
    }

    /** @param Command $command */
    public function cancelConfirmation(User $user, string $locale, array $command): TelegramScreen
    {
        $saved = new SavedMenuState(['command' => $command], 'command');
        $eventId = $saved->integer('eventId');
        $entry = $this->ownedEntry($user, $eventId, $saved->integer('entryId'));
        $event = GameEvent::query()->findOrFail($eventId);
        if ($entry->status !== 'registered' || $event->status !== 'registration' || now()->greaterThanOrEqualTo($event->closes_at)) {
            throw new GameEventUnavailable('events.errors.closed');
        }
        $pet = $entry->pet ?? throw new ModelNotFoundException;
        $text = $this->view->translate('event.cancel_title', $locale).' · '.Str::limit($pet->name, 120)
            ."\n".$this->view->translate('event.cancel_refund', $locale, ['fee' => $entry->fee])
            ."\n".$this->view->translate('event.cancel_deadline', $locale, ['time' => $this->view->dateTime($event->closes_at->toIso8601String(), $user)]);

        return $this->view->screen($text, [[$this->view->button('event.cancel_confirm', 'events.cancel', $locale)]], $locale,
            ['action' => 'events.entry', 'eventId' => $eventId, 'entryId' => $entry->id],
            ['eventCancel' => ['userId' => $user->id, 'eventId' => $eventId, 'entryId' => $entry->id, 'petId' => $entry->pet_id]]);
    }

    /** @param State $state */
    public function cancel(User $user, string $locale, array $state): TelegramScreen
    {
        $saved = new SavedMenuState($state, 'eventCancel');
        $eventId = $saved->integer('eventId');
        $entryId = $saved->integer('entryId');
        $petId = $saved->integer('petId');
        if ($saved->integer('userId') !== $user->id) {
            throw new PetUnavailable('telegram.errors.stale');
        }
        $this->ownedEntry($user, $eventId, $entryId, $petId);
        $entry = $this->cancelEntry->handle($user, $entryId);

        return $this->events->entry($user->refresh(), $locale, ['eventId' => $eventId, 'entryId' => $entry->id],
            $this->view->translate('event.cancelled', $locale));
    }

    private function ownedEntry(User $user, int $eventId, int $entryId, ?int $petId = null): GameEventEntry
    {
        return GameEventEntry::query()->where('user_id', $user->id)->where('game_event_id', $eventId)
            ->when($petId !== null, fn (Builder $query): Builder => $query->where('pet_id', $petId))
            ->whereHas('pet', fn (Builder $query): Builder => $query->where('user_id', $user->id))
            ->with('pet')->findOrFail($entryId);
    }
}

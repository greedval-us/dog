<?php

namespace App\Http\Telegram\Menus;

use App\Models\User;
use App\Modules\Telegram\DTO\TelegramScreen;
use InvalidArgumentException;

/**
 * @phpstan-import-type Command from MenuView
 * @phpstan-import-type State from MenuView
 */
final class EventMenuRoutes
{
    public function __construct(
        private EventMenu $events,
        private EventEntryMenu $entries,
        private EventRegistrationMenu $registration,
    ) {}

    /**
     * @param  Command  $command
     * @param  State  $state
     */
    public function render(User $user, string $locale, array $command, array $state): TelegramScreen
    {
        return match ($command['action'] ?? 'home') {
            'events' => $this->events->index($user, $locale, $command),
            'events.show' => $this->events->show($user, $locale, $command),
            'events.own' => $this->events->own($user, $locale, $command),
            'events.entry' => $this->events->entry($user, $locale, $command),
            'events.results' => $this->events->results($user, $locale, $command),
            'events.divisions' => $this->events->divisions($user, $locale, $command),
            'events.prepare' => $this->registration->prepare($user, $locale, $command),
            'events.draft.show', 'events.draft.option', 'events.draft.member', 'events.draft.stage', 'events.draft.gear' => $this->registration->draft($user, $locale, $command, $state),
            'events.register' => $this->entries->register($user, $locale, $state),
            'events.update' => $this->entries->update($user, $locale, $state),
            'events.cancel-confirm' => $this->entries->cancelConfirmation($user, $locale, $command),
            'events.cancel' => $this->entries->cancel($user, $locale, $state),
            default => throw new InvalidArgumentException('Unknown Telegram menu action.'),
        };
    }
}

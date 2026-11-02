<?php

namespace App\Http\Telegram;

use App\Http\Telegram\Menus\CareMenu;
use App\Http\Telegram\Menus\CatalogueMenu;
use App\Http\Telegram\Menus\DailyWorkMenu;
use App\Http\Telegram\Menus\DogWorkMenu;
use App\Http\Telegram\Menus\EventEntryMenu;
use App\Http\Telegram\Menus\EventMenu;
use App\Http\Telegram\Menus\EventRegistrationMenu;
use App\Http\Telegram\Menus\MenuView;
use App\Http\Telegram\Menus\NotificationMenu;
use App\Http\Telegram\Menus\PetHistoryMenu;
use App\Http\Telegram\Menus\PetMenu;
use App\Http\Telegram\Menus\PlayerMenu;
use App\Http\Telegram\Menus\SkillMenu;
use App\Http\Telegram\Menus\VeterinarianMenu;
use App\Models\User;
use App\Modules\Telegram\DTO\TelegramScreen;
use InvalidArgumentException;

/**
 * @phpstan-import-type Command from MenuView
 * @phpstan-import-type State from MenuView
 */
final class GameMenu
{
    public function __construct(
        private PlayerMenu $players,
        private PetMenu $pets,
        private CareMenu $care,
        private CatalogueMenu $catalogue,
        private SkillMenu $skills,
        private DogWorkMenu $work,
        private VeterinarianMenu $clinic,
        private DailyWorkMenu $dailyWork,
        private NotificationMenu $notifications,
        private PetHistoryMenu $history,
        private EventMenu $events,
        private EventEntryMenu $eventEntries,
        private EventRegistrationMenu $eventRegistration,
    ) {}

    /**
     * @param  Command  $command
     * @param  State  $state
     */
    public function render(User $user, array $command = ['action' => 'home'], array $state = []): TelegramScreen
    {
        $locale = $user->preferredLocale();

        return match ($command['action'] ?? 'home') {
            'home' => $this->players->home($user, $locale),
            'profile' => $this->players->profile($user, $locale),
            'help' => $this->players->help($locale),
            'pets' => $this->pets->index($user, $locale, $command),
            'pet' => $this->pets->show($user, $locale, (int) $command['petId']),
            'care.groups', 'care.variants' => $this->care->options($user, $locale, $command),
            'care.select' => $this->care->select($user, $locale, $command),
            'care.items', 'care.choose', 'care.skip', 'care.confirm' => $this->care->items($user, $locale, $command, $state),
            'care.start' => $this->care->start($user, $locale, $state),
            'care.complete' => $this->care->complete($user, $locale, $command),
            'inventory', 'shop' => $this->catalogue->browse($user, $locale, $command),
            'inventory.item' => $this->catalogue->inventoryItem($user, $locale, $command),
            'shop.offer' => $this->catalogue->offer($user, $locale, $command),
            'shop.confirm' => $this->catalogue->purchaseConfirmation($locale, $state),
            'shop.buy' => $this->catalogue->buy($user, $locale, $state),
            'skills' => $this->skills->index($user, $locale, $command),
            'skills.lesson' => $this->skills->lesson($user, $locale, $command),
            'skills.train' => $this->skills->train($user, $locale, $state),
            'work' => $this->work->index($user, $locale, $command),
            'work.offer' => $this->work->offer($user, $locale, $command),
            'work.start' => $this->work->start($user, $locale, $state),
            'work.complete' => $this->work->complete($user, $locale, $command),
            'clinic' => $this->clinic->index($user, $locale, $command),
            'clinic.diseases' => $this->clinic->diseases($user, $locale, $command),
            'clinic.service' => $this->clinic->service($user, $locale, $command),
            'clinic.perform' => $this->clinic->perform($user, $locale, $state),
            'clinic.history' => $this->clinic->history($user, $locale, $command),
            'daily_work' => $this->dailyWork->index($user, $locale, $command),
            'daily_work.confirm' => $this->dailyWork->confirm($user, $locale, $command),
            'daily_work.complete' => $this->dailyWork->complete($user, $locale, $state),
            'notifications' => $this->notifications->index($user, $locale, $command),
            'notifications.show' => $this->notifications->show($user, $locale, $command),
            'notifications.read' => $this->notifications->read($user, $locale, $state),
            'pet.history' => $this->history->index($user, $locale, $command),
            'pet.history.entry' => $this->history->show($user, $locale, $command),
            'pet.history.events' => $this->history->events($user, $locale, $command),
            'events' => $this->events->index($user, $locale, $command),
            'events.show' => $this->events->show($user, $locale, $command),
            'events.own' => $this->events->own($user, $locale, $command),
            'events.entry' => $this->events->entry($user, $locale, $command),
            'events.results' => $this->events->results($user, $locale, $command),
            'events.divisions' => $this->events->divisions($user, $locale, $command),
            'events.prepare' => $this->eventRegistration->prepare($user, $locale, $command),
            'events.draft.show', 'events.draft.option', 'events.draft.member', 'events.draft.stage', 'events.draft.gear' => $this->eventRegistration->draft($user, $locale, $command, $state),
            'events.register' => $this->eventEntries->register($user, $locale, $state),
            'events.update' => $this->eventEntries->update($user, $locale, $state),
            'events.cancel-confirm' => $this->eventEntries->cancelConfirmation($user, $locale, $command),
            'events.cancel' => $this->eventEntries->cancel($user, $locale, $state),
            default => throw new InvalidArgumentException('Unknown Telegram menu action.'),
        };
    }
}

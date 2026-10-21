<?php

namespace App\Modules\Players\Services;

use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Support\Facades\Lang;

/** Public notification writer; callers commit it alongside a newly completed gameplay fact. */
final class PlayerNotifications
{
    /** @param array<string, string> $name */
    public function achievementUnlocked(User $owner, array $name): void
    {
        $owner->notify(SystemNotification::gameplay('achievement', [
            'ru' => ['name' => $name['ru'] ?? $name['en'] ?? ''],
            'en' => ['name' => $name['en'] ?? $name['ru'] ?? ''],
        ], route('players.achievements', $owner->username, absolute: false)));
    }

    public function petDied(User $owner, int $petId, string $name): void
    {
        $owner->notify(SystemNotification::gameplay('pet_death', [
            'ru' => ['name' => $name], 'en' => ['name' => $name],
        ], route('players.memorial.show', ['user' => $owner->username, 'pet' => $petId], absolute: false)));
    }

    public function eventCompleted(User $owner, int $eventId, string $discipline, bool $show, ?string $petName): void
    {
        $owner->notify(SystemNotification::gameplay($show ? 'show_result' : 'competition_result', [
            'ru' => ['name' => $petName ?? Lang::get('notifications.participant', [], 'ru'), 'discipline' => Lang::get('events.disciplines.'.$discipline, [], 'ru')],
            'en' => ['name' => $petName ?? Lang::get('notifications.participant', [], 'en'), 'discipline' => Lang::get('events.disciplines.'.$discipline, [], 'en')],
        ], route('game-events.show', $eventId, absolute: false)));
    }

    public function litterBorn(User $owner, int $puppyCount): void
    {
        $owner->notify(SystemNotification::gameplay('breeding_success', [
            'ru' => ['count' => (string) $puppyCount], 'en' => ['count' => (string) $puppyCount],
        ], route('puppies.index', absolute: false)));
    }

    public function petSold(User $seller, int $petId, string $name, int $price): void
    {
        $ownerReplacements = ['name' => $name, 'price' => (string) $price];
        $seller->notify(SystemNotification::gameplay('pet_sold', [
            'ru' => $ownerReplacements, 'en' => $ownerReplacements,
        ], route('pets.show', $petId, absolute: false)));
    }
}

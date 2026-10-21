<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterGameEventRequest;
use App\Http\Requests\UpdateGameEventRequest;
use App\Models\GameEvent;
use App\Models\GameEventEntry;
use App\Models\User;
use App\Modules\Pets\Actions\CancelGameEventEntry;
use App\Modules\Pets\Actions\RegisterGameEvent;
use App\Modules\Pets\Actions\UpdateGameEventEntry;
use App\Modules\Pets\DTO\EventRegulationData;
use App\Modules\Pets\Exceptions\GameEventUnavailable;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Queries\GetGameEvents;
use App\Modules\Pets\Services\GameEventSchedule;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GameEventController extends Controller
{
    public function index(Request $request, GetGameEvents $events, GameEventSchedule $schedule): Response
    {
        $user = $this->player($request);
        $filters = $request->validate([
            'frequency' => ['nullable', 'in:daily,weekly,monthly'],
            'kind' => ['nullable', 'in:competition,exhibition'],
            'cursor' => ['nullable', 'string', 'max:1000'],
        ]);
        if ($request->header('X-Inertia-Partial-Component') !== 'GameEvents' || ! $request->hasHeader('X-Inertia-Partial-Data')) {
            $schedule->ensureUpcoming();
        }

        return Inertia::render('GameEvents', [...$events->index($user, $filters), 'serverNow' => now()->toIso8601String()]);
    }

    public function show(Request $request, GameEvent $gameEvent, GetGameEvents $events): Response
    {
        $user = $this->player($request);
        $filters = $request->validate([
            'division' => ['nullable', 'string', 'max:120'],
            'pet' => ['nullable', 'integer', 'min:1'], 'entry_option' => ['nullable', 'string', 'max:120'],
        ]);
        $locale = app()->getLocale();
        $dogs = null;
        $registrationDogs = function () use ($events, $user, $locale, $gameEvent, &$dogs): array {
            return $dogs ??= $events->registrationDogs($user, $locale, $gameEvent);
        };
        $selectionData = null;
        $selection = function () use ($registrationDogs, $filters, &$selectionData): array {
            if ($selectionData !== null) {
                return $selectionData;
            }
            $dog = collect($registrationDogs())->firstWhere('id', (int) ($filters['pet'] ?? 0));
            $optionKey = null;
            foreach ($dog['entryOptions'] ?? [] as $option) {
                if ($option['key'] === ($filters['entry_option'] ?? null) && $option['eligible']) {
                    $optionKey = $option['key'];
                    break;
                }
            }

            return $selectionData = ['petId' => $dog['id'] ?? null, 'optionKey' => $optionKey];
        };
        $state = null;
        $showState = function () use ($events, $user, $gameEvent, $locale, $filters, &$state): array {
            return $state ??= $events->showState($user, $gameEvent->refresh(), $locale, $filters['division'] ?? null,
                isset($filters['pet']) ? (int) $filters['pet'] : null);
        };

        return Inertia::render('GameEventShow', [
            'event' => fn () => $showState()['event'],
            'entry' => fn () => $showState()['entry'],
            'ownEntries' => fn () => $showState()['ownEntries'],
            'dogs' => $registrationDogs,
            'selectedPetId' => fn () => $selection()['petId'],
            'selectedEntryOptionKey' => fn () => $selection()['optionKey'],
            'equipment' => fn () => $events->registrationEquipment($user, $locale),
            'serverNow' => fn () => now()->toIso8601String(),
        ]);
    }

    public function register(RegisterGameEventRequest $request, GameEvent $gameEvent, RegisterGameEvent $register): RedirectResponse
    {
        $user = $this->player($request);

        return $this->respondToEntryChange($gameEvent, 'events.registered', fn (): GameEventEntry => $register->handle(
            $user, $gameEvent->id, (int) $request->validated('pet_id'), $request->validated('plan'),
            $request->validated('gear_ids'), (int) $request->validated('fee'), $request->validated('token'),
        ));
    }

    public function update(UpdateGameEventRequest $request, GameEvent $gameEvent, UpdateGameEventEntry $update): RedirectResponse
    {
        $user = $this->player($request);
        $entry = $this->ownedEntry($gameEvent, $user, $request->validated('pet_id'));

        return $this->respondToEntryChange($gameEvent, 'events.updated', fn (): GameEventEntry => $update->handle(
            $user, $entry->id, $request->validated('plan'), $request->validated('gear_ids'),
        ));
    }

    public function cancel(Request $request, GameEvent $gameEvent, CancelGameEventEntry $cancel): RedirectResponse
    {
        $user = $this->player($request);
        $selection = $request->validate(['pet_id' => ['sometimes', 'integer', 'min:1']]);
        $entry = $this->ownedEntry($gameEvent, $user, $selection['pet_id'] ?? null);

        return $this->respondToEntryChange($gameEvent, 'events.cancelled', fn (): GameEventEntry => $cancel->handle($user, $entry->id));
    }

    private function player(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function ownedEntry(GameEvent $event, User $user, mixed $petId): GameEventEntry
    {
        $entries = $event->entries()->where('user_id', $user->id)->orderBy('id');
        if ($petId !== null) {
            return $entries->where('pet_id', (int) $petId)->firstOrFail();
        }
        if ((clone $entries)->count() > 1) {
            throw ValidationException::withMessages(['pet_id' => __('events.errors.select_dog')]);
        }

        return $entries->firstOrFail();
    }

    /** @param Closure(): GameEventEntry $change */
    private function respondToEntryChange(GameEvent $gameEvent, string $message, Closure $change): RedirectResponse
    {
        try {
            $entry = $change();
        } catch (PetUnavailable|GameEventUnavailable $exception) {
            throw ValidationException::withMessages(['event' => $this->error($exception)]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => __($message)]);

        return to_route('game-events.show', ($gameEvent->discipline === 'conformation' && EventRegulationData::isRegulated($gameEvent)) || EventRegulationData::isGroupRing($gameEvent)
            ? ['gameEvent' => $gameEvent, 'pet' => $entry->pet_id] : $gameEvent);
    }

    private function error(PetUnavailable|GameEventUnavailable $exception): string
    {
        $key = $exception->getMessage();

        return str_starts_with($key, 'events.') ? __($key, [
            'pet_daily_limit' => (int) config('game-events.pet_daily_limit', 2),
            'pet_rest_hours' => (int) config('game-events.pet_rest_hours', 2),
        ]) : __('events.errors.unavailable');
    }
}

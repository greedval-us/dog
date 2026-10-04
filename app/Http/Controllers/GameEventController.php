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
use App\Modules\Pets\Exceptions\GameEventUnavailable;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Queries\GetGameEvents;
use App\Modules\Pets\Services\GameEventProcessor;
use App\Modules\Pets\Services\GameEventSchedule;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GameEventController extends Controller
{
    public function index(Request $request, GetGameEvents $events, GameEventSchedule $schedule, GameEventProcessor $processor): Response
    {
        $user = $this->player($request);
        $filters = $request->validate([
            'frequency' => ['nullable', 'in:daily,weekly,monthly'],
            'kind' => ['nullable', 'in:competition,exhibition'],
            'cursor' => ['nullable', 'string', 'max:1000'],
        ]);
        $schedule->ensureUpcoming();
        $processor->processDue(owner: $user);

        return Inertia::render('GameEvents', $events->index($user, $filters));
    }

    public function show(Request $request, GameEvent $gameEvent, GetGameEvents $events, GameEventProcessor $processor): Response
    {
        $user = $this->player($request);
        $processor->processDue(owner: $user);

        return Inertia::render('GameEventShow', $events->show($user, $gameEvent->fresh(), app()->getLocale()));
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
        $entry = $gameEvent->entries()->where('user_id', $user->id)->firstOrFail();

        return $this->respondToEntryChange($gameEvent, 'events.updated', fn (): GameEventEntry => $update->handle(
            $user, $entry->id, $request->validated('plan'), $request->validated('gear_ids'),
        ));
    }

    public function cancel(Request $request, GameEvent $gameEvent, CancelGameEventEntry $cancel): RedirectResponse
    {
        $user = $this->player($request);
        $entry = $gameEvent->entries()->where('user_id', $user->id)->firstOrFail();

        return $this->respondToEntryChange($gameEvent, 'events.cancelled', fn (): GameEventEntry => $cancel->handle($user, $entry->id));
    }

    private function player(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    /** @param Closure(): GameEventEntry $change */
    private function respondToEntryChange(GameEvent $gameEvent, string $message, Closure $change): RedirectResponse
    {
        try {
            $change();
        } catch (PetUnavailable|GameEventUnavailable $exception) {
            throw ValidationException::withMessages(['event' => $this->error($exception)]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => __($message)]);

        return to_route('game-events.show', $gameEvent);
    }

    private function error(PetUnavailable|GameEventUnavailable $exception): string
    {
        $key = $exception->getMessage();

        return str_starts_with($key, 'events.') ? __($key) : __('events.errors.unavailable');
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Modules\Pets\Services\GameEventProcessor;
use App\Modules\Pets\Services\PetLifecycle;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class SynchronizePetLifecycle
{
    public function __construct(private PetLifecycle $lifecycle, private GameEventProcessor $events) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($request->routeIs('players.show', 'players.achievements', 'players.memorial.*')) {
            $user = $request->route('user');
        }
        if (! $user instanceof User || ! $request->routeIs(
            'dashboard', 'game-events.*', 'breeding.*', 'puppies.*', 'dog-work.*', 'veterinarian.*',
            'pets.care.*', 'pets.skills.*', 'pets.retire', 'pets.thoughts.*', 'pets.appearance.*',
            'pets.history.*', 'pet-slots.*', 'kennel.*', 'care-items',
            'players.show', 'players.achievements', 'players.memorial.*',
        )) {
            return $next($request);
        }
        if ($request->isMethodSafe() && $request->hasHeader('X-Inertia-Partial-Data')
            && (($request->routeIs('game-events.show') && $request->header('X-Inertia-Partial-Component') === 'GameEventShow')
                || ($request->routeIs('game-events.index') && $request->header('X-Inertia-Partial-Component') === 'GameEvents'))) {
            return $next($request);
        }
        $at = CarbonImmutable::now()->startOfSecond();
        $limit = (int) config('game-events.processing.http_batch_size', 0);
        if ($limit > 0) {
            $this->events->processDue($at, $user, $limit);
        }
        if ($this->events->hasDueRegistrations($user, $at)) {
            if (! $request->isMethodSafe() && $request->hasHeader('X-Inertia')) {
                Inertia::flash('toast', ['type' => 'error', 'message' => __('events.errors.processing')]);

                return redirect()->back(303)->withErrors(['event' => __('events.errors.processing')]);
            }
            abort_unless($request->isMethodSafe(), 503, __('events.errors.processing'), ['Retry-After' => '60']);

            return $next($request);
        }
        if (! $request->routeIs('pets.care.complete')) {
            $this->lifecycle->synchronizeOwner($user, $at);
        }

        return $next($request);
    }
}

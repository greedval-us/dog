<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Modules\Pets\Services\GameEventProcessor;
use App\Modules\Pets\Services\PetLifecycle;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SynchronizePetLifecycle
{
    public function __construct(private PetLifecycle $lifecycle, private GameEventProcessor $events) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User && ! $request->routeIs('assets.image', 'breeds.image', 'pet-scene', 'players.avatar.show', 'players.show', 'players.memorial.*')) {
            $this->events->processDue(owner: $user);
            if (! $request->routeIs('pets.care.complete')) {
                $this->lifecycle->synchronizeOwner($user);
            }
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\Players\Queries\GetPlayerAchievements;
use App\Modules\Players\Queries\GetPlayerProfile;
use App\Modules\Players\Services\PlayerProgress;
use Inertia\Inertia;
use Inertia\Response;

class PlayerAchievementController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(User $user, GetPlayerAchievements $achievements, GetPlayerProfile $profile, PlayerProgress $progress): Response
    {
        $progress->refreshAchievements($user);
        $items = $achievements->handle($user, app()->getLocale());

        return Inertia::render('players/Achievements', [
            'player' => $profile->handle($user)->toArray(),
            'achievements' => $items,
            'unlockedCount' => count(array_filter($items, static fn (array $achievement): bool => $achievement['unlockedAt'] !== null)),
            'totalCount' => count($items),
        ]);
    }
}

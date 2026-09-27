<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\Players\Queries\GetDailyWork;
use App\Modules\Players\Queries\GetPlayerDogs;
use App\Modules\Players\Queries\GetPlayerProfile;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlayerProfileController extends Controller
{
    public function __invoke(Request $request, User $user, GetPlayerProfile $profile, GetPlayerDogs $dogs, GetDailyWork $work): Response
    {
        return Inertia::render('PlayerProfile', [
            'player' => $profile->handle($user)->toArray(),
            'dogs' => $dogs->handle($user, app()->getLocale()),
            'isOwner' => $request->user()->is($user),
            'dailyWork' => $request->user()->is($user) ? $work->handle($user, app()->getLocale()) : null,
            'inventoryCount' => $request->user()->is($user) ? $user->inventoryItems()->count() : null,
            'avatarLimits' => $request->user()->is($user) ? config('doglive.avatar') : null,
        ]);
    }
}

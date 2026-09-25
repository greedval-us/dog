<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\Players\Queries\GetPlayerProfile;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlayerProfileController extends Controller
{
    public function __invoke(Request $request, User $user, GetPlayerProfile $profile): Response
    {
        return Inertia::render('PlayerProfile', [
            'player' => $profile->handle($user)->toArray(),
            'isOwner' => $request->user()->is($user),
            'avatarLimits' => $request->user()->is($user) ? config('doglive.avatar') : null,
        ]);
    }
}

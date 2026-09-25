<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Queries\GetPlayerProfile;
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
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Queries\GetPrimaryPet;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, GetPrimaryPet $getPrimaryPet): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return Inertia::render('Dashboard', [
            'canClaimStarterPet' => $user->canClaimStarterPet(),
            'pet' => $getPrimaryPet->handle($user, app()->getLocale())?->toArray(),
        ]);
    }
}

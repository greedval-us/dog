<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\Appearance\Queries\GetPetAppearance;
use App\Modules\Pets\Queries\GetPetCare;
use App\Modules\Pets\Queries\GetPetSlots;
use App\Modules\Pets\Queries\GetPrimaryPet;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, GetPrimaryPet $getPrimaryPet, GetPetAppearance $getAppearance, GetPetSlots $getSlots, GetPetCare $getCare): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $request->validate(['pet' => ['sometimes', 'integer', 'min:1']]);
        $pet = $getPrimaryPet->handle($user, app()->getLocale(), $request->has('pet') ? $request->integer('pet') : null);

        return Inertia::render('Dashboard', [
            'canClaimStarterPet' => $user->canClaimStarterPet(),
            'slots' => $getSlots->handle($user),
            'pet' => $pet?->toArray(),
            'care' => $pet === null ? null : $getCare->handle($user, $pet->id, app()->getLocale()),
            'appearance' => $pet === null ? null : $getAppearance->handle($user, $pet->id, app()->getLocale())->toArray(),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\Appearance\Queries\GetPetAppearance;
use App\Modules\Pets\Queries\GetPetCare;
use App\Modules\Pets\Queries\GetPetSkills;
use App\Modules\Pets\Queries\GetPetSlots;
use App\Modules\Pets\Queries\GetPrimaryPet;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, GetPrimaryPet $getPrimaryPet, GetPetAppearance $getAppearance, GetPetSlots $getSlots, GetPetCare $getCare, GetPetSkills $getSkills): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $request->validate(['pet' => ['sometimes', 'integer', 'min:1']]);
        $petId = $request->has('pet')
            ? $user->pets()->findOrFail($request->integer('pet'), ['id'])->id
            : $user->pets()->active()->oldest('id')->value('id');

        return Inertia::render('Dashboard', [
            'canClaimStarterPet' => fn () => $user->canClaimStarterPet(),
            'slots' => fn () => $getSlots->handle($user),
            'pet' => fn () => $petId === null ? null : $getPrimaryPet->handle($user, app()->getLocale(), $petId)?->toArray(),
            'care' => Inertia::defer(fn () => $petId === null ? null : $getCare->handle($user, $petId, app()->getLocale()), 'care', rescue: true),
            'skills' => Inertia::defer(fn () => $petId === null ? null : $getSkills->handle($user, $petId, app()->getLocale()), 'skills', rescue: true),
            'appearance' => Inertia::defer(fn () => $petId === null ? null : $getAppearance->handle($user, $petId, app()->getLocale())->toArray(), 'appearance', rescue: true),
        ]);
    }
}

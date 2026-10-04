<?php

namespace App\Http\Controllers;

use App\Models\Pet;
use App\Models\User;
use App\Modules\Players\Queries\GetPetMemorial;
use App\Modules\Players\Queries\GetPlayerProfile;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PetMemorialController extends Controller
{
    public function index(Request $request, User $user, GetPlayerProfile $profile, GetPetMemorial $memorial): Response
    {
        $request->validate(['cursor' => ['nullable', 'string', 'max:1000']]);

        return Inertia::render('PetMemorial', [
            'player' => $profile->handle($user)->toArray(),
            'isOwner' => $request->user()->is($user),
            'pets' => $memorial->handle($user, app()->getLocale()),
        ]);
    }

    public function show(Request $request, User $user, Pet $pet, GetPlayerProfile $profile, GetPetMemorial $memorial): Response
    {
        $snapshot = $memorial->pet($user, $pet->id, app()->getLocale());

        return Inertia::render('PetMemorialShow', [
            'player' => $profile->handle($user)->toArray(),
            'isOwner' => $request->user()->is($user),
            'pet' => $snapshot['pet']->toArray(),
            'appearance' => $snapshot['appearance']->toArray(),
            'learnedSkills' => $snapshot['learnedSkills'],
        ]);
    }
}

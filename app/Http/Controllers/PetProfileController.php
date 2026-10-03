<?php

namespace App\Http\Controllers;

use App\Models\Pet;
use App\Modules\Pets\Queries\GetPetProfile;
use Inertia\Inertia;
use Inertia\Response;

class PetProfileController extends Controller
{
    public function __invoke(Pet $pet, GetPetProfile $profile): Response
    {
        return Inertia::render('PetProfile', [
            'profile' => $profile->handle($pet->id, app()->getLocale()),
        ]);
    }
}

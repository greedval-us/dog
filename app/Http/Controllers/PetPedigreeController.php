<?php

namespace App\Http\Controllers;

use App\Models\Pet;
use App\Modules\Pets\Queries\GetPetPedigree;
use Inertia\Inertia;
use Inertia\Response;

class PetPedigreeController extends Controller
{
    public function __invoke(Pet $pet, GetPetPedigree $pedigree): Response
    {
        return Inertia::render('PetPedigree', [
            'pedigree' => $pedigree->handle($pet->id, app()->getLocale()),
        ]);
    }
}

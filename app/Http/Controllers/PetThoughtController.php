<?php

namespace App\Http\Controllers;

use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Actions\RecordPetThought;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PetThoughtController extends Controller
{
    public function store(Request $request, Pet $pet, RecordPetThought $thought): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $pet->user_id === $user->id, 404);
        $thought->handle($user, $pet->id);

        return response()->noContent();
    }
}

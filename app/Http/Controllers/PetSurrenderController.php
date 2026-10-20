<?php

namespace App\Http\Controllers;

use App\Http\Requests\PetOwnerRequest;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Actions\SurrenderPet;
use App\Modules\Pets\Exceptions\PetUnavailable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PetSurrenderController extends Controller
{
    public function __invoke(PetOwnerRequest $request, Pet $pet, SurrenderPet $surrender): RedirectResponse
    {
        $owner = $request->user();
        abort_unless($owner instanceof User, 403);
        try {
            $surrender->handle($owner, $pet->id);
        } catch (PetUnavailable $exception) {
            throw ValidationException::withMessages(['ownership' => __($exception->getMessage())]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => __('ownership.surrendered')]);

        return to_route('dashboard');
    }
}

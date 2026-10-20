<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePetBoardingRequest;
use App\Models\User;
use App\Modules\Pets\Actions\UpdatePetBoarding;
use App\Modules\Pets\Enums\PetBoardingPlan;
use App\Modules\Pets\Exceptions\PetUnavailable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PetBoardingController extends Controller
{
    public function update(UpdatePetBoardingRequest $request, UpdatePetBoarding $update): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        try {
            $update->handle($user, PetBoardingPlan::from($request->validated('plan')));
        } catch (PetUnavailable $exception) {
            throw ValidationException::withMessages(['plan' => __($exception->getMessage())]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Boarding settings updated.')]);

        return to_route('profile.edit');
    }
}

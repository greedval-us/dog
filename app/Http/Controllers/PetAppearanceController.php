<?php

namespace App\Http\Controllers;

use App\Http\Requests\SelectPetAssetRequest;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Appearance\Actions\SelectPetAsset;
use App\Modules\Appearance\Exceptions\AppearanceUnavailable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PetAppearanceController extends Controller
{
    public function update(SelectPetAssetRequest $request, Pet $pet, SelectPetAsset $select): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        try {
            $select->handle($user, $pet->id, $request->integer('asset_id'));
        } catch (AppearanceUnavailable $exception) {
            throw ValidationException::withMessages(['asset_id' => __($exception->getMessage())]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your dog’s appearance has been updated.')]);

        return back();
    }
}

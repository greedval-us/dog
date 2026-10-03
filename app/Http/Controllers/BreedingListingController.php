<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublishBreedingListingRequest;
use App\Models\BreedingListing;
use App\Models\User;
use App\Modules\Pets\Actions\PublishBreedingListing;
use App\Modules\Pets\Exceptions\BreedingUnavailable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BreedingListingController extends Controller
{
    public function store(PublishBreedingListingRequest $request, PublishBreedingListing $publish): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        try {
            $publish->handle($user, (int) $request->validated('pet_id'), (int) $request->validated('price'));
        } catch (BreedingUnavailable $exception) {
            throw ValidationException::withMessages(['listing' => __(str_replace('breeding.errors.', 'breeding.', $exception->getMessage()))]);
        }

        return to_route('breeding.index');
    }

    public function destroy(Request $request, BreedingListing $listing, PublishBreedingListing $publish): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        try {
            $publish->withdraw($user, $listing);
        } catch (BreedingUnavailable $exception) {
            throw ValidationException::withMessages(['listing' => __(str_replace('breeding.errors.', 'breeding.', $exception->getMessage()))]);
        }

        return to_route('breeding.index');
    }
}

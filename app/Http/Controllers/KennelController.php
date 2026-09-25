<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdoptStarterPetRequest;
use App\Models\User;
use App\Modules\Kennel\Actions\AdoptStarterPet;
use App\Modules\Kennel\DTO\StarterBreedData;
use App\Modules\Kennel\Exceptions\StarterBreedUnavailable;
use App\Modules\Kennel\Exceptions\StarterPetAlreadyClaimed;
use App\Modules\Kennel\Queries\GetStarterBreeds;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class KennelController extends Controller
{
    public function index(Request $request, GetStarterBreeds $getStarterBreeds): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return Inertia::render('Kennel', [
            'canClaimStarterPet' => $user->canClaimStarterPet(),
            'breeds' => $getStarterBreeds->handle(app()->getLocale())
                ->map(fn (StarterBreedData $breed): array => $breed->toArray()),
        ]);
    }

    public function store(AdoptStarterPetRequest $request, AdoptStarterPet $adopt): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        try {
            $adopt->handle($user, $request->toData());
        } catch (StarterPetAlreadyClaimed) {
            throw ValidationException::withMessages([
                'adoption' => __('Your first dog is available only once, before you have any pets.'),
            ]);
        } catch (StarterBreedUnavailable) {
            throw ValidationException::withMessages([
                'dog_id' => __('This breed is currently unavailable. Please choose another.'),
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your first dog is home!')]);

        return to_route('dashboard');
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdoptStarterPetRequest;
use App\Http\Requests\PurchaseKennelPetRequest;
use App\Models\User;
use App\Modules\Kennel\Actions\AdoptStarterPet;
use App\Modules\Kennel\Actions\PurchaseKennelPet;
use App\Modules\Kennel\DTO\StarterBreedData;
use App\Modules\Kennel\Exceptions\AdoptionUnavailable;
use App\Modules\Kennel\Exceptions\StarterBreedUnavailable;
use App\Modules\Kennel\Exceptions\StarterPetAlreadyClaimed;
use App\Modules\Kennel\Queries\GetStarterBreeds;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class KennelController extends Controller
{
    public function index(Request $request, GetStarterBreeds $getStarterBreeds): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $user->refresh();

        return Inertia::render('Kennel', [
            'canClaimStarterPet' => $user->canClaimStarterPet(),
            'freeSlots' => max(0, $user->pet_slots - $user->pets()->active()->count()),
            'price' => config('doglive.kennel_price'),
            'adoptionToken' => (string) Str::uuid(),
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
        } catch (AdoptionUnavailable $exception) {
            throw ValidationException::withMessages(['adoption' => __($exception->getMessage())]);
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

    public function purchase(PurchaseKennelPetRequest $request, PurchaseKennelPet $purchase): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        try {
            $pet = $purchase->handle($user, $request->toData());
        } catch (AdoptionUnavailable $exception) {
            throw ValidationException::withMessages(['adoption' => __($exception->getMessage())]);
        } catch (StarterBreedUnavailable) {
            throw ValidationException::withMessages([
                'dog_id' => __('This breed is currently unavailable. Please choose another.'),
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your new dog is home!')]);

        return to_route('dashboard', $pet === null ? [] : ['pet' => $pet->id]);
    }
}

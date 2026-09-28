<?php

namespace App\Http\Controllers;

use App\Http\Requests\StartPetCareRequest;
use App\Models\PetCareAction;
use App\Models\User;
use App\Modules\Inventory\Exceptions\ItemUnavailable;
use App\Modules\Pets\Actions\CompletePetCare;
use App\Modules\Pets\Actions\StartPetCare;
use App\Modules\Pets\Exceptions\PetUnavailable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use InvalidArgumentException;

class PetCareController extends Controller
{
    public function store(StartPetCareRequest $request, int $pet, StartPetCare $start): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        try {
            $start->handle($user, $pet, $request->validated('variant'), $request->itemIds(), $request->validated('token'));
        } catch (PetUnavailable|ItemUnavailable $exception) {
            throw ValidationException::withMessages(['care' => __($exception->getMessage())]);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['care' => __('Refresh the page before starting another action.')]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The activity has started.')]);

        return to_route('dashboard', ['pet' => $pet]);
    }

    public function complete(Request $request, int $pet, CompletePetCare $complete): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $validated = $request->validate(['token' => ['required', 'uuid']]);

        try {
            $changed = $complete->handle($user, $pet, $validated['token']);
        } catch (PetUnavailable $exception) {
            throw ValidationException::withMessages(['care' => __($exception->getMessage())]);
        }

        if ($changed) {
            $care = PetCareAction::query()->where('user_id', $user->id)->where('pet_id', $pet)->where('token', strtolower($validated['token']))->firstOrFail();
            $negative = array_filter($care->incidents ?? [], fn (array $incident): bool => $incident['effect']['kind'] === 'debuff');
            Inertia::flash('toast', $negative !== []
                ? ['type' => 'warning', 'message' => __('An item caused an adverse event. See your dog’s recent events.')]
                : ['type' => 'success', 'message' => __($care->incidents === null
                    ? 'Care completed. Your dog’s condition has been updated.'
                    : 'An item granted a bonus effect. See your dog’s recent events.')]);
        }

        return to_route('dashboard', ['pet' => $pet]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\Pets\Actions\RetirePet;
use App\Modules\Pets\Exceptions\PetUnavailable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class RetirePetController extends Controller
{
    public function __invoke(Request $request, int $pet, RetirePet $retire): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        try {
            $retire->handle($user, $pet);
        } catch (PetUnavailable $exception) {
            throw ValidationException::withMessages(['retirement' => __($exception->getMessage())]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your dog has retired. Their card is preserved in the Pet Memorial.')]);

        return to_route('players.memorial.show', ['user' => $user->username, 'pet' => $pet]);
    }
}

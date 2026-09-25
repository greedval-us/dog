<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchasePetSlotRequest;
use App\Models\User;
use App\Modules\Pets\Actions\PurchasePetSlot;
use App\Modules\Pets\Exceptions\PetUnavailable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PetSlotController extends Controller
{
    public function store(PurchasePetSlotRequest $request, PurchasePetSlot $purchase): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        try {
            $purchase->handle($user, $request->toData());
        } catch (PetUnavailable $exception) {
            throw ValidationException::withMessages(['slot' => __($exception->getMessage())]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dog slot unlocked.')]);

        return back();
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\PetOwnerRequest;
use App\Http\Requests\PublishPetSaleRequest;
use App\Http\Requests\PurchasePetSaleRequest;
use App\Models\Pet;
use App\Models\PetSaleListing;
use App\Models\User;
use App\Modules\Pets\Actions\PublishPetSale;
use App\Modules\Pets\Actions\PurchasePetSale;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Queries\GetPetMarket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PetSaleController extends Controller
{
    public function index(Request $request, GetPetMarket $market): Response
    {
        $user = $this->player($request);

        return Inertia::render('PetMarket', [
            ...$market->handle($user, app()->getLocale()),
            'freeSlots' => max(0, $user->pet_slots - $user->pets()->active()->count()),
            'purchaseToken' => (string) Str::uuid(),
            'serverNow' => now()->toIso8601String(),
        ]);
    }

    public function store(PublishPetSaleRequest $request, Pet $pet, PublishPetSale $publish): RedirectResponse
    {
        try {
            $publish->handle($this->player($request), $pet->id, (int) $request->validated('price'));
        } catch (PetUnavailable $exception) {
            throw ValidationException::withMessages(['ownership' => __($exception->getMessage())]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => __('ownership.listed')]);

        return to_route('pet-market.index');
    }

    public function destroy(PetOwnerRequest $request, Pet $pet, PublishPetSale $publish): RedirectResponse
    {
        try {
            $publish->withdraw($this->player($request), $pet->id);
        } catch (PetUnavailable $exception) {
            throw ValidationException::withMessages(['ownership' => __($exception->getMessage())]);
        }

        return back();
    }

    public function purchase(PurchasePetSaleRequest $request, PetSaleListing $listing, PurchasePetSale $purchase): RedirectResponse
    {
        try {
            $receipt = $purchase->handle($this->player($request), $listing->id, (int) $request->validated('expected_price'), $request->validated('purchase_token'));
        } catch (PetUnavailable $exception) {
            throw ValidationException::withMessages(['ownership' => __($exception->getMessage())]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => __('ownership.purchased')]);

        return to_route('dashboard', ['pet' => $receipt->pet_id]);
    }

    private function player(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}

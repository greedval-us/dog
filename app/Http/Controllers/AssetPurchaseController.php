<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchasePetAssetRequest;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Appearance\Actions\PurchasePetAsset;
use App\Modules\Appearance\Exceptions\AppearanceUnavailable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AssetPurchaseController extends Controller
{
    public function store(PurchasePetAssetRequest $request, Pet $pet, PurchasePetAsset $purchase): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        try {
            $purchase->handle($user, $pet->id, $request->toData());
        } catch (AppearanceUnavailable $exception) {
            throw ValidationException::withMessages(['asset_id' => __($exception->getMessage())]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Appearance unlocked for your account and applied.')]);

        return back();
    }
}

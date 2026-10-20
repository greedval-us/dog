<?php

namespace App\Modules\Pets\Actions;

use App\Models\BreedingListing;
use App\Models\CurrencyTransaction;
use App\Models\Pet;
use App\Models\PetHistoryEntry;
use App\Models\PetSaleListing;
use App\Models\PetSalePurchase;
use App\Models\User;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Queries\PetOwnershipEligibility;
use App\Modules\Pets\Services\PetLifecycle;
use App\Modules\Players\Enums\PlayerStatus;
use App\Modules\Players\Exceptions\InsufficientFunds;
use App\Modules\Players\Services\PlayerWallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PurchasePetSale
{
    public function __construct(private PetLifecycle $lifecycle, private PetOwnershipEligibility $eligibility, private PlayerWallet $wallet, private PetDecayCalculator $decay) {}

    public function handle(User $user, int $listingId, int $expectedPrice, string $token): PetSalePurchase
    {
        $token = strtolower($token);
        if (! Str::isUuid($token) || $listingId < 1 || $expectedPrice < 1 || $expectedPrice > PublishPetSale::MAXIMUM_PRICE) {
            throw new PetUnavailable('ownership.errors.purchase');
        }
        $candidate = PetSaleListing::query()->findOrFail($listingId);
        $ownerIds = array_values(array_unique(array_filter([$user->id, $candidate->user_id], static fn (?int $id): bool => $id !== null)));
        sort($ownerIds);
        foreach (User::query()->whereIn('id', $ownerIds)->orderBy('id')->get() as $owner) {
            $this->lifecycle->synchronizeOwner($owner);
        }

        return DB::transaction(function () use ($user, $listingId, $expectedPrice, $token, $ownerIds, $candidate): PetSalePurchase {
            $owners = User::query()->whereIn('id', $ownerIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $buyer = $owners->get($user->id);
            if ($buyer === null || $buyer->status !== PlayerStatus::Active) {
                throw new PetUnavailable('Your account is blocked.');
            }
            $existing = PetSalePurchase::query()->where('buyer_id', $buyer->id)->where('token', $token)->first();
            if ($existing !== null) {
                if ($existing->listing_id !== $listingId || $existing->price_paid !== $expectedPrice) {
                    throw new PetUnavailable('ownership.errors.replay');
                }

                return $existing;
            }
            $pet = Pet::query()->lockForUpdate()->findOrFail($candidate->pet_id);
            $listing = PetSaleListing::query()->lockForUpdate()->findOrFail($listingId);
            $seller = $owners->get($listing->user_id);
            if (! $listing->is_active || $listing->pet_id !== $pet->id || $listing->user_id !== $candidate->user_id
                || $seller === null || $seller->id === $buyer->id || $seller->status !== PlayerStatus::Active || $pet->user_id !== $seller->id) {
                throw new PetUnavailable('ownership.errors.unavailable');
            }
            foreach ($owners as $owner) {
                $this->lifecycle->assertCanAdvance($owner);
            }
            $pet->advanceTo(now(), $this->decay);
            if (($reason = $this->eligibility->reason($pet)) !== null) {
                throw new PetUnavailable($reason);
            }
            if ($listing->price !== $expectedPrice) {
                throw new PetUnavailable('ownership.errors.price_changed');
            }
            if ($buyer->pets()->active()->count() >= $buyer->pet_slots) {
                throw new PetUnavailable('You need a free dog slot. Unlock a place on the My dog page.');
            }
            $operationKey = 'pet-sale:'.$buyer->id.':'.$token;
            if (CurrencyTransaction::query()->whereIn('user_id', $ownerIds)->whereRaw('LOWER(operation_key) = ?', [$operationKey])->exists()) {
                throw new PetUnavailable('ownership.errors.receipt');
            }
            try {
                $this->wallet->change($buyer, 'coins', -$listing->price, $operationKey, 'pet_purchase');
            } catch (InsufficientFunds) {
                throw new PetUnavailable('ownership.errors.funds');
            }
            $this->wallet->change($seller, 'coins', $listing->price, $operationKey, 'pet_sale');
            $pet->forceFill(['user_id' => $buyer->id, 'is_favorite' => false, 'portrait_asset_id' => null, 'background_asset_id' => null])->save();
            $listing->update(['is_active' => false]);
            BreedingListing::query()->where('pet_id', $pet->id)->update(['is_active' => false]);
            $receipt = PetSalePurchase::query()->create([
                'listing_id' => $listing->id, 'pet_id' => $pet->id, 'buyer_id' => $buyer->id,
                'seller_id' => $seller->id, 'token' => $token, 'price_paid' => $listing->price,
            ]);
            PetHistoryEntry::query()->firstOrCreate(['pet_id' => $pet->id, 'source_key' => 'ownership:sale:'.$receipt->id], [
                'kind' => 'action', 'event_code' => 'ownership_changed',
                'title' => ['ru' => 'Смена владельца', 'en' => 'New owner'],
                'details' => ['sellerId' => $seller->id, 'buyerId' => $buyer->id, 'coins' => $listing->price],
                'occurred_at' => now(),
            ]);

            return $receipt;
        }, attempts: 3);
    }
}

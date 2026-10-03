<?php

namespace App\Modules\Pets\Actions;

use App\Models\CurrencyTransaction;
use App\Models\Puppy;
use App\Models\PuppyPlacement;
use App\Models\User;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Services\PetLifecycle;
use App\Modules\Pets\Services\PuppyLifecycle;
use App\Modules\Pets\Services\PuppyPlacementService;
use App\Modules\Players\Enums\PlayerStatus;
use App\Modules\Players\Exceptions\InsufficientFunds;
use App\Modules\Players\Services\PlayerWallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PurchasePuppy
{
    public function __construct(private PuppyLifecycle $puppies, private PetLifecycle $lifecycle, private PuppyPlacementService $placements, private PlayerWallet $wallet) {}

    public function handle(User $user, int $puppyId, string $name, int $expectedPrice, string $token): PuppyPlacement
    {
        $name = trim($name);
        $token = strtolower($token);
        if (! Str::isUuid($token) || $name === '' || mb_strlen($name) > 64 || $puppyId < 1 || $expectedPrice < 1) {
            throw new PetUnavailable('Invalid puppy purchase.');
        }
        $this->puppies->synchronize($puppyId);
        $candidate = Puppy::query()->findOrFail($puppyId);
        $ownerIds = array_values(array_unique(array_filter([$user->id, $candidate->user_id], static fn (?int $id): bool => $id !== null)));
        sort($ownerIds);
        foreach (User::query()->whereIn('id', $ownerIds)->orderBy('id')->get() as $owner) {
            $this->lifecycle->synchronizeOwner($owner);
        }

        try {
            return DB::transaction(function () use ($user, $puppyId, $name, $expectedPrice, $token, $ownerIds): PuppyPlacement {
                $owners = User::query()->whereIn('id', $ownerIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $buyer = $owners->get($user->id);
                if ($buyer === null || $buyer->status !== PlayerStatus::Active) {
                    throw new PetUnavailable('Your account is blocked.');
                }
                $existing = $this->placements->replay($buyer, $puppyId, $name, $token, 'purchase', $expectedPrice);
                if ($existing !== null) {
                    return $existing;
                }
                $puppy = Puppy::query()->lockForUpdate()->findOrFail($puppyId);
                if (! in_array($puppy->status, ['listed', 'kennel'], true) || $puppy->user_id === $buyer->id) {
                    throw new PetUnavailable('This puppy is no longer available to purchase.');
                }
                $seller = $puppy->status === 'listed' ? $owners->get($puppy->user_id) : null;
                if ($puppy->status === 'listed' && ($seller === null || $seller->status !== PlayerStatus::Active
                    || $puppy->expires_at->lessThanOrEqualTo(now()))) {
                    throw new PetUnavailable('This puppy is no longer available to purchase.');
                }
                $price = $puppy->status === 'kennel' ? config('doglive.kennel_price') : $puppy->sale_price;
                if (! is_int($price) || $price < 1 || $price !== $expectedPrice) {
                    throw new PetUnavailable('The price has changed. Refresh the page before purchasing.');
                }
                if ($buyer->pets()->active()->count() >= $buyer->pet_slots) {
                    throw new PetUnavailable('You need a free dog slot. Unlock a place on the My dog page.');
                }
                $operationKey = 'puppy-purchase:'.$token;
                if (CurrencyTransaction::query()->whereIn('user_id', $ownerIds)->whereRaw('LOWER(operation_key) = ?', [$operationKey])->exists()) {
                    throw new PetUnavailable('This puppy purchase was already paid for, but its receipt is unavailable. Check your dogs before trying again.');
                }
                try {
                    $this->wallet->change($buyer, 'coins', -$price, $operationKey, 'puppy_purchase');
                } catch (InsufficientFunds) {
                    throw new PetUnavailable('You do not have enough coins for this puppy.');
                }
                if ($seller !== null) {
                    $this->wallet->change($seller, 'coins', $price, $operationKey, 'puppy_sale');
                }

                return $this->placements->place($buyer, $puppy, $name, $token, 'purchase', $price, $seller?->id);
            }, attempts: 3);
        } catch (PetUnavailable $exception) {
            $this->puppies->synchronize($puppyId);
            throw $exception;
        }
    }
}

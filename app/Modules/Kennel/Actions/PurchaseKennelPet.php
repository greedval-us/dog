<?php

namespace App\Modules\Kennel\Actions;

use App\Models\CurrencyTransaction;
use App\Models\Dog;
use App\Models\KennelPurchase;
use App\Models\User;
use App\Modules\Kennel\DTO\PurchaseKennelPetData;
use App\Modules\Kennel\Exceptions\AdoptionUnavailable;
use App\Modules\Kennel\Exceptions\StarterBreedUnavailable;
use App\Modules\Kennel\Generators\StarterPetGenerator;
use App\Modules\Pets\Enums\PetStat;
use App\Modules\Pets\Services\PetLifecycle;
use App\Modules\Players\Enums\PlayerStatus;
use App\Modules\Players\Exceptions\InsufficientFunds;
use App\Modules\Players\Services\PlayerProgress;
use App\Modules\Players\Services\PlayerWallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PurchaseKennelPet
{
    public function __construct(private StarterPetGenerator $generator, private PlayerWallet $wallet, private PetLifecycle $lifecycle, private PlayerProgress $progress) {}

    public function handle(User $user, PurchaseKennelPetData $data): KennelPurchase
    {
        $token = strtolower($data->token);
        $name = trim($data->name);

        if (! Str::isUuid($token) || $data->dogId < 1 || $data->expectedPrice < 1 || $name === '' || mb_strlen($name) > 64) {
            throw new AdoptionUnavailable('Invalid dog purchase.');
        }
        $this->lifecycle->synchronizeOwner($user);

        return DB::transaction(function () use ($user, $data, $token, $name): KennelPurchase {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($owner->status !== PlayerStatus::Active) {
                throw new AdoptionUnavailable('Your account is blocked.');
            }

            $operationKey = 'kennel:'.$token;
            $existing = KennelPurchase::query()->where('user_id', $owner->id)->where('token', $token)->first();

            if ($existing !== null) {
                if ($existing->dog_id !== $data->dogId || $existing->pet_name !== $name || $existing->price_paid !== $data->expectedPrice) {
                    throw new AdoptionUnavailable('The token was already used for a different dog purchase.');
                }

                return $existing;
            }

            if (CurrencyTransaction::query()->whereBelongsTo($owner)->whereRaw('LOWER(operation_key) = ?', [$operationKey])->exists()) {
                throw new AdoptionUnavailable('This purchase was already paid for, but its receipt is unavailable. Check your dogs before making a new purchase.');
            }

            if ($owner->canClaimStarterPet()) {
                throw new AdoptionUnavailable('Your first dog is free. Refresh the kennel to claim it.');
            }

            if ($owner->pets()->active()->count() >= $owner->pet_slots) {
                throw new AdoptionUnavailable('You need a free dog slot. Unlock a place on the My dog page.');
            }

            $price = config('doglive.kennel_price');

            if (! is_int($price) || $price < 1 || $price !== $data->expectedPrice) {
                throw new AdoptionUnavailable('The price has changed. Refresh the page before purchasing.');
            }

            $dog = Dog::query()->find($data->dogId);

            if ($dog === null || ! $dog->canBeAdopted()) {
                throw new StarterBreedUnavailable;
            }

            $this->lifecycle->assertCanAdvance($owner);

            try {
                $entry = $this->wallet->change($owner, 'coins', -$price, $operationKey, 'kennel_purchase');
            } catch (InsufficientFunds) {
                throw new AdoptionUnavailable('You do not have enough coins for this dog.');
            }

            $pet = $dog->newPet($this->generator->generate(
                name: $name,
                coatColors: array_keys($dog->coat_colors),
                breedPotentials: $dog->only(array_map(fn (PetStat $stat): string => $stat->potentialColumn(), PetStat::cases())),
                excludedCoatColors: config('doglive.breeding_rare_colors', []),
            ));
            $pet->user()->associate($owner);
            $pet->save();

            User::query()->whereKey($owner->id)->whereNull('starter_pet_claimed_at')
                ->update(['starter_pet_claimed_at' => now()]);

            $purchase = KennelPurchase::query()->create([
                'user_id' => $owner->id,
                'dog_id' => $dog->id,
                'pet_id' => $pet->id,
                'pet_name' => $name,
                'token' => $token,
                'price_paid' => $price,
                'currency_transaction_id' => $entry->id,
            ]);
            $this->progress->refreshAchievements($owner);

            return $purchase;
        }, attempts: 3);
    }
}

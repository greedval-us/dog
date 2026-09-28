<?php

namespace App\Modules\Kennel\Actions;

use App\Models\CurrencyTransaction;
use App\Models\Dog;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Kennel\DTO\PurchaseKennelPetData;
use App\Modules\Kennel\Exceptions\AdoptionUnavailable;
use App\Modules\Kennel\Exceptions\StarterBreedUnavailable;
use App\Modules\Kennel\Generators\StarterPetGenerator;
use App\Modules\Players\Enums\PlayerStatus;
use App\Modules\Players\Exceptions\InsufficientFunds;
use App\Modules\Players\Services\PlayerWallet;
use Illuminate\Support\Facades\DB;

final class PurchaseKennelPet
{
    public function __construct(private StarterPetGenerator $generator, private PlayerWallet $wallet) {}

    public function handle(User $user, PurchaseKennelPetData $data): ?Pet
    {
        return DB::transaction(function () use ($user, $data): ?Pet {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($owner->status !== PlayerStatus::Active) {
                throw new AdoptionUnavailable('Your account is blocked.');
            }

            $operationKey = 'kennel:'.$data->token;

            if (CurrencyTransaction::query()->whereBelongsTo($owner)->where('operation_key', $operationKey)->exists()) {
                return null;
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

            try {
                $this->wallet->change($owner, 'coins', -$price, $operationKey, 'kennel_purchase');
            } catch (InsufficientFunds) {
                throw new AdoptionUnavailable('You do not have enough coins for this dog.');
            }

            $pet = $dog->newPet($this->generator->generate(
                name: $data->name,
                coatColors: array_keys($dog->coat_colors),
            ));
            $pet->user()->associate($owner);
            $pet->save();

            User::query()->whereKey($owner->id)->whereNull('starter_pet_claimed_at')
                ->update(['starter_pet_claimed_at' => now()]);

            return $pet;
        }, attempts: 3);
    }
}

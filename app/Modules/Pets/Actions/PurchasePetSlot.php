<?php

namespace App\Modules\Pets\Actions;

use App\Models\User;
use App\Modules\Pets\DTO\PurchasePetSlotData;
use App\Modules\Pets\Exceptions\PetUnavailable;
use Illuminate\Support\Facades\DB;

final class PurchasePetSlot
{
    public function handle(User $user, PurchasePetSlotData $data): void
    {
        DB::transaction(function () use ($user, $data): void {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($data->slot < 2 || $data->slot > 9 || ! in_array($data->currency, ['coins', 'gems'], true)) {
                throw new PetUnavailable('This dog slot is unavailable.');
            }

            if ($data->slot <= $owner->pet_slots) {
                return;
            }

            if ($data->slot !== $owner->pet_slots + 1) {
                throw new PetUnavailable('Unlock dog slots in order.');
            }

            $price = config('pet_slots.prices.'.$data->slot.'.'.$data->currency);

            if (! is_int($price) || $price < 1 || $price !== $data->expectedPrice) {
                throw new PetUnavailable('The price has changed. Refresh the page before purchasing.');
            }

            $charged = User::query()->whereKey($owner->id)
                ->where('pet_slots', $owner->pet_slots)
                ->where($data->currency, '>=', $price)
                ->decrement($data->currency, $price, ['pet_slots' => $data->slot]);

            if ($charged !== 1) {
                throw new PetUnavailable('You do not have enough currency for this dog slot.');
            }
        }, 3);
    }
}

<?php

namespace App\Modules\Pets\Queries;

use App\Models\User;

final class GetPetSlots
{
    /** @return list<array{number: int, unlocked: bool, purchasable: bool, coins: int, gems: int, pet: array{id: int, name: string}|null}> */
    public function handle(User $user): array
    {
        $unlocked = (int) User::query()->whereKey($user->id)->value('pet_slots');
        $pets = $user->pets()->active()->oldest('id')->get(['id', 'name']);
        $slots = [];

        for ($number = 1; $number <= max(9, $pets->count()); $number++) {
            $pet = $pets->get($number - 1);
            $slots[] = [
                'number' => $number,
                'unlocked' => $number <= $unlocked || $pet !== null,
                'purchasable' => $pet === null && $number <= 9 && $number === $unlocked + 1,
                'coins' => (int) config('pet_slots.prices.'.$number.'.coins', 0),
                'gems' => (int) config('pet_slots.prices.'.$number.'.gems', 0),
                'pet' => $pet === null ? null : ['id' => $pet->id, 'name' => $pet->name],
            ];
        }

        return $slots;
    }
}

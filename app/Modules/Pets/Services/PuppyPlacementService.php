<?php

namespace App\Modules\Pets\Services;

use App\Models\Dog;
use App\Models\Puppy;
use App\Models\PuppyPlacement;
use App\Models\User;
use App\Modules\Pets\DTO\NewPetData;
use App\Modules\Pets\Enums\PetStat;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Players\Services\PlayerProgress;

final class PuppyPlacementService
{
    public function __construct(private PlayerProgress $progress) {}

    public function replay(User $owner, int $puppyId, string $name, string $token, string $kind, int $price): ?PuppyPlacement
    {
        $placement = PuppyPlacement::query()->where('operation_token', $token)->first();
        if ($placement !== null && ($placement->user_id !== $owner->id || $placement->puppy_id !== $puppyId || $placement->name !== $name
            || $placement->kind !== $kind || $placement->price !== $price)) {
            throw new PetUnavailable('The token was already used for a different puppy placement.');
        }

        return $placement;
    }

    /** The caller holds the owner and puppy locks and commits the payment and placement together. */
    public function place(User $owner, Puppy $puppy, string $name, string $token, string $kind, int $price, ?int $sellerId = null): PuppyPlacement
    {
        if ($owner->pets()->active()->count() >= $owner->pet_slots) {
            throw new PetUnavailable('You need a free dog slot. Unlock a place on the My dog page.');
        }
        $potentials = [];
        foreach (PetStat::cases() as $stat) {
            $potentials[$stat->potentialColumn()] = $puppy->getAttribute($stat->potentialColumn());
        }
        $dog = Dog::query()->findOrFail($puppy->dog_id);
        $pet = $dog->newPet(new NewPetData(name: $name, sex: $puppy->sex, coatColor: $puppy->coat_color, potentials: $potentials, exterior: $puppy->exterior));
        $pet->forceFill(['father_id' => $puppy->father_id, 'mother_id' => $puppy->mother_id, 'generation' => $puppy->generation]);
        $pet->user()->associate($owner);
        $pet->save();
        $puppy->forceFill(['status' => 'placed', 'user_id' => $owner->id, 'pet_id' => $pet->id, 'placed_at' => now(), 'sale_price' => null])->save();
        User::query()->whereKey($owner->id)->whereNull('starter_pet_claimed_at')->update(['starter_pet_claimed_at' => now()]);
        $placement = PuppyPlacement::query()->create([
            'user_id' => $owner->id, 'seller_id' => $sellerId, 'puppy_id' => $puppy->id, 'pet_id' => $pet->id,
            'operation_token' => $token, 'kind' => $kind, 'price' => $price, 'name' => $name,
        ]);
        $this->progress->refreshAchievements($owner);

        return $placement;
    }
}

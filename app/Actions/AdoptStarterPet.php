<?php

namespace App\Actions;

use App\Data\AdoptStarterPetData;
use App\Data\NewPetData;
use App\Enums\PetSex;
use App\Exceptions\StarterBreedUnavailable;
use App\Exceptions\StarterPetAlreadyClaimed;
use App\Models\Dog;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class AdoptStarterPet
{
    public function handle(User $user, AdoptStarterPetData $data): Pet
    {
        return DB::transaction(function () use ($user, $data): Pet {
            $claimed = User::query()
                ->whereKey($user->id)
                ->eligibleForStarterPet()
                ->update(['starter_pet_claimed_at' => now()]);

            if ($claimed !== 1) {
                throw new StarterPetAlreadyClaimed;
            }

            $dog = Dog::query()->find($data->dogId);

            if ($dog === null || ! $dog->canBeAdopted()) {
                throw new StarterBreedUnavailable;
            }

            $colors = array_keys($dog->coat_colors);
            $sexes = PetSex::cases();
            $pet = $dog->newPet(new NewPetData(
                name: $data->name,
                sex: $sexes[random_int(0, count($sexes) - 1)],
                coatColor: $colors[random_int(0, count($colors) - 1)],
            ));
            $pet->user()->associate($user);
            $pet->save();

            return $pet;
        }, attempts: 3);
    }
}

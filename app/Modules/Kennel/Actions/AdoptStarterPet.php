<?php

namespace App\Modules\Kennel\Actions;

use App\Models\Dog;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Kennel\DTO\AdoptStarterPetData;
use App\Modules\Kennel\Exceptions\StarterBreedUnavailable;
use App\Modules\Kennel\Exceptions\StarterPetAlreadyClaimed;
use App\Modules\Kennel\Generators\StarterPetGenerator;
use Illuminate\Support\Facades\DB;

final class AdoptStarterPet
{
    public function __construct(private StarterPetGenerator $generator) {}

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

            $pet = $dog->newPet($this->generator->generate(
                name: $data->name,
                coatColors: array_keys($dog->coat_colors),
            ));
            $pet->user()->associate($user);
            $pet->save();

            return $pet;
        }, attempts: 3);
    }
}

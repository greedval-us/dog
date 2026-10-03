<?php

namespace App\Modules\Kennel\Actions;

use App\Models\Dog;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Kennel\DTO\AdoptStarterPetData;
use App\Modules\Kennel\Exceptions\AdoptionUnavailable;
use App\Modules\Kennel\Exceptions\StarterBreedUnavailable;
use App\Modules\Kennel\Exceptions\StarterPetAlreadyClaimed;
use App\Modules\Kennel\Generators\StarterPetGenerator;
use App\Modules\Pets\Enums\PetStat;
use App\Modules\Pets\Services\PetLifecycle;
use App\Modules\Players\Enums\PlayerStatus;
use App\Modules\Players\Services\PlayerProgress;
use Illuminate\Support\Facades\DB;

final class AdoptStarterPet
{
    public function __construct(private StarterPetGenerator $generator, private PetLifecycle $lifecycle, private PlayerProgress $progress) {}

    public function handle(User $user, AdoptStarterPetData $data): Pet
    {
        $this->lifecycle->synchronizeOwner($user);

        return DB::transaction(function () use ($user, $data): Pet {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($owner->status !== PlayerStatus::Active) {
                throw new AdoptionUnavailable('Your account is blocked.');
            }

            if ($owner->pets()->active()->count() >= $owner->pet_slots) {
                throw new AdoptionUnavailable('You need a free dog slot. Unlock a place on the My dog page.');
            }

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
                breedPotentials: $dog->only(array_map(fn (PetStat $stat): string => $stat->potentialColumn(), PetStat::cases())),
            ));
            $pet->user()->associate($user);
            $pet->save();
            $this->progress->refreshAchievements($owner);

            return $pet;
        }, attempts: 3);
    }
}

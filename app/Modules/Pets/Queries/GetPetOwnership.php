<?php

namespace App\Modules\Pets\Queries;

use App\Models\PetSaleListing;
use App\Models\User;
use Illuminate\Support\Facades\Lang;

final class GetPetOwnership
{
    public function __construct(private PetOwnershipEligibility $eligibility) {}

    /** @return array{listing:array{id:int,price:int}|null,reason:string|null} */
    public function handle(User $owner, int $petId, string $locale): array
    {
        $pet = $owner->pets()->findOrFail($petId);
        $listing = PetSaleListing::query()->where('pet_id', $pet->id)->where('user_id', $owner->id)->where('is_active', true)->first();
        $reason = $this->eligibility->reason($pet);

        return [
            'listing' => $listing === null ? null : ['id' => $listing->id, 'price' => $listing->price],
            'reason' => $reason === null ? null : Lang::string($reason, [], $locale),
        ];
    }
}
